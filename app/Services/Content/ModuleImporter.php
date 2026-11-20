<?php

namespace App\Services\Content;

use App\Models\Course;
use App\Models\Module;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Imports a module file. Re-importing the same module id updates it in place:
 * questions are matched by their id, removed questions are archived (soft-deleted)
 * so attempt history keeps pointing at them.
 */
class ModuleImporter
{
    public function __construct(
        private ModuleParser $parser,
        private ModuleValidator $validator,
    ) {}

    /**
     * Validates the file and compares it with the database. With a `$board` (an upload in the UI)
     * the module goes into that board and is stored as draft until it is marked verified by hand;
     * without one (the CLI) the frontmatter `course` decides the board and `status` is trusted.
     */
    public function analyze(string $raw, ?Course $board = null): ImportAnalysis
    {
        try {
            $parsed = $this->parser->parse($raw);
        } catch (ModuleParseException $exception) {
            $report = new ValidationReport;
            $report->error($exception->getMessage());

            return new ImportAnalysis($report);
        }

        $report = $this->validator->validate($parsed);

        if ($report->isValid() && $board === null && $report->definition['course'] === null) {
            $report->error('`course` (slug + title) wajib untuk impor lewat terminal. Lewat aplikasi, board yang menentukan.');
        }

        if (! $report->isValid()) {
            return new ImportAnalysis($report);
        }

        if ($board !== null) {
            $this->applyBoard($report, $board);
        }

        $diff = $this->diff($report->definition, $board);

        if ($diff['previous_board'] !== null) {
            $report->warning("Modul `{$report->definition['id']}` sudah ada di board “{$diff['previous_board']}”. Kalau dilanjutkan, modul itu dipindah ke board “{$diff['board']}”.");
        }

        if (! $diff['is_new_module'] && ($diff['module_changed'] || $diff['new'] !== [] || $diff['changed'] !== [] || $diff['removed'] !== [])
            && $report->definition['version'] <= $diff['previous_version']) {
            $report->warning("Isi modul berubah tapi `version` tidak naik (tetap {$report->definition['version']}). Naikkan version supaya perubahan bisa dilacak.");
        }

        return new ImportAnalysis($report, $diff);
    }

    /**
     * @throws InvalidArgumentException when the file is invalid.
     */
    public function import(string $raw, ?Course $board = null): Module
    {
        $analysis = $this->analyze($raw, $board);

        if (! $analysis->isValid()) {
            throw new InvalidArgumentException('Modul tidak valid: '.implode(' | ', $analysis->report->errors));
        }

        $definition = $analysis->report->definition;

        return DB::transaction(function () use ($definition, $board): Module {
            // A board's title and description are edited in the app; the CLI only creates missing boards.
            $course = $board ?? Course::query()->firstOrCreate(
                ['slug' => $definition['course']['slug']],
                ['title' => $definition['course']['title'], 'description' => $definition['course']['description']],
            );

            $module = Module::query()->updateOrCreate(
                ['slug' => $definition['id']],
                [
                    'course_id' => $course->id,
                    'title' => $definition['title'],
                    'summary' => $definition['summary'],
                    'position' => $definition['order'],
                    'version' => $definition['version'],
                    'status' => $definition['status'],
                    'body' => $definition['body'],
                    'objectives' => $definition['objectives'],
                    'sources' => $definition['sources'],
                    'tested_on' => $definition['tested_on'],
                    'quiz_sizes' => (object) $definition['quiz'],
                    'content_updated_on' => $definition['updated'],
                    'content_hash' => $this->moduleHash($definition),
                    'imported_at' => now(),
                ],
            );

            $existing = $module->questions()->withTrashed()->get()->keyBy('key');
            $keep = [];

            foreach ($definition['questions'] as $position => $question) {
                $attributes = $this->questionAttributes($question, $position);
                $current = $existing->get($question['id']);
                $keep[] = $question['id'];

                if ($current === null) {
                    $module->questions()->create(['key' => $question['id'], ...$attributes]);

                    continue;
                }

                $current->fill($attributes)->save();

                if ($current->trashed()) {
                    $current->restore();
                }
            }

            $module->questions()->whereNotIn('key', $keep)->get()->each(fn (Question $question) => $question->delete());

            return $module->fresh();
        });
    }

    /**
     * Uploads land in the board they were made from: a different `course` in the file is reported,
     * not used, and a claimed `verified` becomes draft because nobody has checked it yet.
     */
    private function applyBoard(ValidationReport $report, Course $board): void
    {
        $fileCourse = $report->definition['course'];

        if ($fileCourse !== null && $fileCourse['slug'] !== $board->slug) {
            $report->warning("File menyebut course `{$fileCourse['slug']}`, tapi modul akan dimasukkan ke board “{$board->title}” (board tempat kamu mengunggah).");
        }

        if ($report->definition['status'] === 'verified') {
            $report->warning('File menulis `status: verified`. Modul hasil unggahan selalu disimpan sebagai draft; tandai terverifikasi di halaman modul setelah kamu mengecek isinya.');
        }

        $report->definition['course'] = ['slug' => $board->slug, 'title' => $board->title, 'description' => $board->description];
        $report->definition['status'] = 'draft';
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{is_new_course: bool, is_new_module: bool, board: string, previous_board: string|null, previous_version: int|null, module_changed: bool, new: list<string>, changed: list<string>, removed: list<string>, unchanged: list<string>}
     */
    private function diff(array $definition, ?Course $board): array
    {
        $module = Module::query()->with('course')->where('slug', $definition['id'])->first();
        $target = $board ?? Course::query()->where('slug', $definition['course']['slug'])->first();
        $incoming = collect($definition['questions'])->keyBy('id');
        $boardTitle = $target->title ?? $definition['course']['title'];
        $previousBoard = $module !== null && $target !== null && $module->course_id !== $target->id ? $module->course->title : null;

        if ($module === null) {
            return [
                'is_new_course' => $target === null,
                'is_new_module' => true,
                'board' => $boardTitle,
                'previous_board' => null,
                'previous_version' => null,
                'module_changed' => true,
                'new' => $incoming->keys()->all(),
                'changed' => [],
                'removed' => [],
                'unchanged' => [],
            ];
        }

        $current = $module->questions()->get()->keyBy('key');
        $new = [];
        $changed = [];
        $unchanged = [];

        foreach ($incoming as $key => $question) {
            $existing = $current->get($key);

            if ($existing === null) {
                $new[] = (string) $key;
            } elseif ($existing->content_hash !== $this->questionHash($question)) {
                $changed[] = (string) $key;
            } else {
                $unchanged[] = (string) $key;
            }
        }

        return [
            'is_new_course' => $target === null,
            'is_new_module' => false,
            'board' => $boardTitle,
            'previous_board' => $previousBoard,
            'previous_version' => $module->version,
            'module_changed' => $previousBoard !== null || $module->content_hash !== $this->moduleHash($definition),
            'new' => $new,
            'changed' => $changed,
            'removed' => $current->keys()->diff($incoming->keys())->map(fn ($key): string => (string) $key)->values()->all(),
            'unchanged' => $unchanged,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>
     */
    private function questionAttributes(array $question, int $position): array
    {
        return [
            'tier' => $question['tier'],
            'type' => $question['type'],
            'objective' => $question['objective'],
            'position' => $position,
            'prompt' => $question['prompt'],
            'context' => $question['context'],
            'spec' => $question['spec'],
            'explanation' => $question['explanation'],
            'solution' => $question['solution'],
            'source_url' => $question['source'],
            'content_hash' => $this->questionHash($question),
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     */
    private function questionHash(array $question): string
    {
        return hash('sha256', json_encode($question, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    /**
     * Hash of the module's own content: questions are tracked individually, and the board
     * is not content (renaming a board must not make every module look changed).
     *
     * @param  array<string, mixed>  $definition
     */
    private function moduleHash(array $definition): string
    {
        $meta = $definition;
        unset($meta['questions'], $meta['course']);

        return hash('sha256', json_encode($meta, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
