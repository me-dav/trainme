<?php

namespace App\Services\Content;

use App\Enums\QuestionType;
use App\Enums\Tier;
use App\Services\Grading\AnswerGrader;

/**
 * Validates a parsed module file against format version 1 (see content/FORMAT.md)
 * and self-tests every question: accepted answers must grade correct, `tests.fail` must not.
 */
class ModuleValidator
{
    public const FORMAT_VERSION = 1;

    private const SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const QUESTION_ID = '/^[a-z0-9][a-z0-9_-]{0,49}$/';

    public function __construct(private AnswerGrader $grader) {}

    /**
     * @param  array{meta: array<string, mixed>, body: string}  $parsed
     */
    public function validate(array $parsed): ValidationReport
    {
        $report = new ValidationReport;
        $meta = $parsed['meta'];

        if (($meta['format'] ?? null) !== self::FORMAT_VERSION) {
            $report->error('`format` harus bernilai '.self::FORMAT_VERSION.'.');
        }

        $id = $this->slug($report, $meta, 'id', '`id` modul');
        $course = array_key_exists('course', $meta) ? $this->course($report, $meta['course']) : null;
        $title = $this->requiredString($report, $meta, 'title', '`title`');
        $summary = $this->optionalString($report, $meta, 'summary', '`summary`');
        $updated = $this->optionalString($report, $meta, 'updated', '`updated` (tulis sebagai string, mis. \'2026-11-19\')');

        $order = $meta['order'] ?? 0;
        if (! is_int($order) || $order < 0) {
            $report->error('`order` harus bilangan bulat ≥ 0.');
        }

        $version = $meta['version'] ?? null;
        if (! is_int($version) || $version < 1) {
            $report->error('`version` wajib, bilangan bulat ≥ 1.');
        }

        $status = $meta['status'] ?? 'draft';
        if (! in_array($status, ['draft', 'verified'], true)) {
            $report->error('`status` harus `draft` atau `verified`.');
        }

        $testedOn = $this->stringList($report, $meta['tested_on'] ?? [], '`tested_on`');
        $quiz = $this->quizSizes($report, $meta['quiz'] ?? []);
        $objectives = $this->objectives($report, $meta['objectives'] ?? []);
        $sources = $this->sources($report, $meta['sources'] ?? []);
        $questions = $this->questions($report, $meta['questions'] ?? null, array_column($objectives, 'id'));

        if ($parsed['body'] === '') {
            $report->warning('Body Markdown (materi) kosong.');
        }

        foreach (Tier::cases() as $tier) {
            $available = count(array_filter($questions, fn (array $question): bool => $question['tier'] === $tier->value));
            $size = $quiz[$tier->value] ?? config('dojo.quiz_sizes.'.$tier->value);

            if ($available === 0) {
                $report->warning("{$tier->label()} belum punya soal — tier ini tidak bisa dikerjakan.");
            } elseif ($available < $size) {
                $report->warning("{$tier->label()}: hanya {$available} soal untuk kuis berukuran {$size}; semua soal akan dipakai.");
            }
        }

        if ($report->errors === []) {
            $report->definition = [
                'id' => $id,
                'course' => $course,
                'title' => $title,
                'summary' => $summary,
                'order' => $order,
                'version' => $version,
                'status' => $status,
                'updated' => $updated,
                'tested_on' => $testedOn,
                'quiz' => $quiz,
                'objectives' => $objectives,
                'sources' => $sources,
                'questions' => $questions,
                'body' => $parsed['body'],
            ];
        }

        return $report;
    }

    /**
     * Optional: an upload into a board ignores it; the CLI needs it to know the board.
     *
     * @return array{slug: string, title: string, description: string|null}
     */
    private function course(ValidationReport $report, mixed $course): array
    {
        if (! is_array($course)) {
            $report->error('`course` wajib berupa mapping dengan `slug` dan `title`.');

            return ['slug' => '', 'title' => '', 'description' => null];
        }

        return [
            'slug' => $this->slug($report, $course, 'slug', '`course.slug`'),
            'title' => $this->requiredString($report, $course, 'title', '`course.title`'),
            'description' => $this->optionalString($report, $course, 'description', '`course.description`'),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function quizSizes(ValidationReport $report, mixed $quiz): array
    {
        if (! is_array($quiz)) {
            $report->error('`quiz` harus mapping tier → jumlah soal, mis. `A: 10`.');

            return [];
        }

        $sizes = [];

        foreach ($quiz as $tier => $size) {
            if (Tier::tryFrom((string) $tier) === null) {
                $report->error("`quiz`: tier `{$tier}` tidak dikenal (pakai A, B, atau C).");
            } elseif (! is_int($size) || $size < 1) {
                $report->error("`quiz.{$tier}` harus bilangan bulat ≥ 1.");
            } else {
                $sizes[(string) $tier] = $size;
            }
        }

        return $sizes;
    }

    /**
     * @return list<array{id: string, text: string}>
     */
    private function objectives(ValidationReport $report, mixed $objectives): array
    {
        if (! is_array($objectives) || ! array_is_list($objectives)) {
            $report->error('`objectives` harus berupa list.');

            return [];
        }

        $result = [];

        foreach ($objectives as $index => $objective) {
            $label = '`objectives['.$index.']`';

            if (! is_array($objective)) {
                $report->error("{$label} harus mapping dengan `id` dan `text`.");

                continue;
            }

            $objectiveId = $this->slug($report, $objective, 'id', "{$label}.id");

            if (in_array($objectiveId, array_column($result, 'id'), true)) {
                $report->error("{$label}: id `{$objectiveId}` dipakai lebih dari sekali.");
            }

            $result[] = ['id' => $objectiveId, 'text' => $this->requiredString($report, $objective, 'text', "{$label}.text")];
        }

        return $result;
    }

    /**
     * @return list<array{title: string, url: string}>
     */
    private function sources(ValidationReport $report, mixed $sources): array
    {
        if (! is_array($sources) || ! array_is_list($sources)) {
            $report->error('`sources` harus berupa list.');

            return [];
        }

        if ($sources === []) {
            $report->warning('`sources` kosong — materi sebaiknya selalu punya sumber.');
        }

        $result = [];

        foreach ($sources as $index => $source) {
            $label = '`sources['.$index.']`';

            if (! is_array($source)) {
                $report->error("{$label} harus mapping dengan `title` dan `url`.");

                continue;
            }

            $result[] = [
                'title' => $this->requiredString($report, $source, 'title', "{$label}.title"),
                'url' => $this->url($report, $source['url'] ?? null, "{$label}.url", required: true) ?? '',
            ];
        }

        return $result;
    }

    /**
     * @param  list<string>  $objectiveIds
     * @return list<array<string, mixed>>
     */
    private function questions(ValidationReport $report, mixed $questions, array $objectiveIds): array
    {
        if (! is_array($questions) || ! array_is_list($questions) || $questions === []) {
            $report->error('`questions` wajib berupa list dan tidak boleh kosong.');

            return [];
        }

        $result = [];
        $seen = [];

        foreach ($questions as $index => $question) {
            $label = 'Soal #'.($index + 1);

            if (! is_array($question)) {
                $report->error("{$label} harus berupa mapping.");

                continue;
            }

            $questionId = $question['id'] ?? null;

            if (! is_string($questionId) || preg_match(self::QUESTION_ID, $questionId) !== 1) {
                $report->error("{$label}: `id` wajib, huruf kecil/angka/-/_ (mis. `a01`).");
                $questionId = null;
            } else {
                $label = "Soal `{$questionId}`";

                if (isset($seen[$questionId])) {
                    $report->error("{$label}: id dipakai lebih dari sekali.");
                }

                $seen[$questionId] = true;
            }

            $normalized = $this->question($report, $question, $label, $objectiveIds);

            if ($normalized !== null && $questionId !== null) {
                $result[] = ['id' => $questionId, ...$normalized];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  list<string>  $objectiveIds
     * @return array<string, mixed>|null
     */
    private function question(ValidationReport $report, array $question, string $label, array $objectiveIds): ?array
    {
        $errorsBefore = count($report->errors);
        $tier = Tier::tryFrom((string) ($question['tier'] ?? ''));
        $type = QuestionType::tryFrom((string) ($question['type'] ?? ''));

        if ($tier === null) {
            $report->error("{$label}: `tier` harus A, B, atau C.");
        }

        if ($type === null) {
            $report->error("{$label}: `type` harus command, text, atau scenario.");
        }

        $objective = $question['objective'] ?? null;

        if ($objective !== null && ! in_array($objective, $objectiveIds, true)) {
            $report->error("{$label}: objective `{$objective}` tidak ada di daftar `objectives`.");
        }

        $prompt = $this->requiredString($report, $question, 'prompt', "{$label}: `prompt`");
        $context = $this->optionalString($report, $question, 'context', "{$label}: `context`");
        $explanation = $this->optionalString($report, $question, 'explanation', "{$label}: `explanation`");
        $solution = $this->optionalString($report, $question, 'solution', "{$label}: `solution`");
        $source = $this->url($report, $question['source'] ?? null, "{$label}: `source`", required: false);
        $tests = $this->tests($report, $question['tests'] ?? [], $label);

        if ($source === null) {
            $report->warning("{$label}: tidak punya `source` — sebaiknya setiap soal bisa dilacak ke dokumentasi.");
        }

        $spec = match ($type) {
            QuestionType::Command, QuestionType::Text => $this->answerSpec($report, $question, $label, $type),
            QuestionType::Scenario => $this->scenarioSpec($report, $question, $label, $tests),
            null => [],
        };

        if (count($report->errors) > $errorsBefore || $tier === null || $type === null) {
            return null;
        }

        $spec['tests'] = $tests;
        $this->selfTest($report, $type, $spec, $label);

        return [
            'tier' => $tier->value,
            'type' => $type->value,
            'objective' => $objective,
            'prompt' => $prompt,
            'context' => $context,
            'explanation' => $explanation,
            'solution' => $solution,
            'source' => $source,
            'spec' => $spec,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array{answers: list<string>, patterns: list<string>, flags_any_order: bool}
     */
    private function answerSpec(ValidationReport $report, array $question, string $label, QuestionType $type): array
    {
        $answers = $question['answers'] ?? null;

        if (! is_array($answers) || $answers === [] || ! array_is_list($answers)) {
            $report->error("{$label}: `answers` wajib berupa list minimal satu jawaban.");
            $answers = [];
        }

        foreach ($answers as $answer) {
            if (! is_string($answer) || trim($answer) === '') {
                $report->error("{$label}: setiap `answers` harus string tidak kosong — beri tanda kutip pada angka, mis. '0644'.");
            }
        }

        $flagsAnyOrder = $question['flags_any_order'] ?? false;

        if (! is_bool($flagsAnyOrder)) {
            $report->error("{$label}: `flags_any_order` harus true/false.");
        }

        if ($type === QuestionType::Text && $flagsAnyOrder === true) {
            $report->error("{$label}: `flags_any_order` hanya untuk type command.");
        }

        return [
            'answers' => array_values(array_filter($answers, 'is_string')),
            'patterns' => $this->patterns($report, $question['patterns'] ?? [], "{$label}: `patterns`", $type === QuestionType::Text ? 'i' : ''),
            'flags_any_order' => $flagsAnyOrder === true,
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array{pass: list<string>, fail: list<string>}  $tests
     * @return array{checklist: list<array<string, mixed>>, pass_score?: float}
     */
    private function scenarioSpec(ValidationReport $report, array $question, string $label, array $tests): array
    {
        $checklist = $question['checklist'] ?? null;

        if (! is_array($checklist) || $checklist === [] || ! array_is_list($checklist)) {
            $report->error("{$label}: scenario wajib punya `checklist` (list).");

            return ['checklist' => []];
        }

        $items = [];
        $ids = [];

        foreach ($checklist as $index => $item) {
            $itemLabel = "{$label}: checklist #".($index + 1);

            if (! is_array($item)) {
                $report->error("{$itemLabel} harus mapping.");

                continue;
            }

            $itemId = $this->slug($report, $item, 'id', "{$itemLabel}.id");

            if (in_array($itemId, $ids, true)) {
                $report->error("{$itemLabel}: id `{$itemId}` dipakai lebih dari sekali.");
            }

            $ids[] = $itemId;

            foreach (['required', 'forbid'] as $flag) {
                if (isset($item[$flag]) && ! is_bool($item[$flag])) {
                    $report->error("{$itemLabel}: `{$flag}` harus true/false.");
                }
            }

            if (($item['required'] ?? false) === true && ($item['forbid'] ?? false) === true) {
                $report->error("{$itemLabel}: tidak bisa `required` dan `forbid` sekaligus.");
            }

            $patterns = $this->patterns($report, $item['patterns'] ?? null, "{$itemLabel}.patterns", 'im');

            if ($patterns === []) {
                $report->error("{$itemLabel}: `patterns` wajib minimal satu regex.");
            }

            $items[] = [
                'id' => $itemId,
                'label' => $this->requiredString($report, $item, 'label', "{$itemLabel}.label"),
                'patterns' => $patterns,
                'required' => ($item['required'] ?? false) === true,
                'forbid' => ($item['forbid'] ?? false) === true,
            ];
        }

        if ($items !== [] && array_filter($items, fn (array $item): bool => ! $item['forbid']) === []) {
            $report->error("{$label}: checklist butuh minimal satu item yang bukan `forbid`.");
        }

        if ($tests['pass'] === []) {
            $report->error("{$label}: scenario wajib punya minimal satu `tests.pass` (contoh jawaban benar) untuk menguji checklist.");
        }

        $spec = ['checklist' => $items];

        if (array_key_exists('pass_score', $question)) {
            $passScore = $question['pass_score'];

            if ((! is_int($passScore) && ! is_float($passScore)) || $passScore <= 0 || $passScore > 1) {
                $report->error("{$label}: `pass_score` harus angka antara 0 dan 1.");
            } else {
                $spec['pass_score'] = (float) $passScore;
            }
        }

        return $spec;
    }

    /**
     * @return array{pass: list<string>, fail: list<string>}
     */
    private function tests(ValidationReport $report, mixed $tests, string $label): array
    {
        if (! is_array($tests)) {
            $report->error("{$label}: `tests` harus mapping dengan `pass` dan/atau `fail`.");

            return ['pass' => [], 'fail' => []];
        }

        return [
            'pass' => $this->stringList($report, $tests['pass'] ?? [], "{$label}: `tests.pass`"),
            'fail' => $this->stringList($report, $tests['fail'] ?? [], "{$label}: `tests.fail`"),
        ];
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function selfTest(ValidationReport $report, QuestionType $type, array $spec, string $label): void
    {
        $mustPass = $type === QuestionType::Scenario ? $spec['tests']['pass'] : [...$spec['answers'], ...$spec['tests']['pass']];

        foreach ($mustPass as $sample) {
            if (! $this->grader->grade($type, $spec, $sample)->isCorrect) {
                $report->error("{$label}: self-test gagal — jawaban yang seharusnya BENAR dinilai salah: `".$this->preview($sample).'`');
            }
        }

        foreach ($spec['tests']['fail'] as $sample) {
            if ($this->grader->grade($type, $spec, $sample)->isCorrect) {
                $report->error("{$label}: self-test gagal — jawaban yang seharusnya SALAH dinilai benar: `".$this->preview($sample).'`');
            }
        }
    }

    /**
     * @return list<string>
     */
    private function patterns(ValidationReport $report, mixed $patterns, string $label, string $modifiers): array
    {
        if ($patterns === null) {
            return [];
        }

        if (! is_array($patterns) || ! array_is_list($patterns)) {
            $report->error("{$label} harus berupa list regex.");

            return [];
        }

        $valid = [];

        foreach ($patterns as $pattern) {
            if (! is_string($pattern) || $pattern === '') {
                $report->error("{$label}: setiap pattern harus string tidak kosong.");

                continue;
            }

            if (@preg_match(AnswerGrader::delimit($pattern, $modifiers), '') === false) {
                $report->error("{$label}: regex tidak valid `{$pattern}` (".preg_last_error_msg().').');

                continue;
            }

            $valid[] = $pattern;
        }

        return $valid;
    }

    /**
     * @return list<string>
     */
    private function stringList(ValidationReport $report, mixed $value, string $label): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            $report->error("{$label} harus berupa list string.");

            return [];
        }

        $strings = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                $report->error("{$label}: semua item harus string — beri tanda kutip.");

                continue;
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function slug(ValidationReport $report, array $data, string $key, string $label): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || preg_match(self::SLUG, $value) !== 1) {
            $report->error("{$label} wajib, format slug huruf kecil-angka-strip (mis. `linux-permission`).");

            return '';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requiredString(ValidationReport $report, array $data, string $key, string $label): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            $report->error("{$label} wajib diisi (string).");

            return '';
        }

        return trim($value);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function optionalString(ValidationReport $report, array $data, string $key, string $label): ?string
    {
        $value = $data[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (! is_string($value)) {
            $report->error("{$label} harus string.");

            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function url(ValidationReport $report, mixed $value, string $label, bool $required): ?string
    {
        if ($value === null || $value === '') {
            if ($required) {
                $report->error("{$label} wajib diisi.");
            }

            return null;
        }

        if (! is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false || ! preg_match('~^https?://~i', $value)) {
            $report->error("{$label} harus URL http(s) yang valid.");

            return null;
        }

        return $value;
    }

    private function preview(string $sample): string
    {
        $oneLine = (string) preg_replace('/\s*\n\s*/', ' ⏎ ', trim($sample));

        return mb_strlen($oneLine) > 80 ? mb_substr($oneLine, 0, 77).'...' : $oneLine;
    }
}
