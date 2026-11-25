<?php

namespace App\Services\Ai;

use App\Models\Course;
use App\Models\Module;
use App\Services\Content\ModuleImporter;
use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes a module file from a document in two stages, because the time goes into writing,
 * not reading:
 *
 * 1. one request reads the document and writes the outline (id, title, objectives, sources, sections);
 * 2. four requests run at the same time, each with the whole document and the outline: the material
 *    and the tier A, B and C questions.
 *
 * The parts are assembled into one file and checked by the same importer as a manual upload. Errors
 * are traced back to the part that caused them, and only that part is rewritten.
 */
class ModuleGenerator
{
    /**
     * @var array<string, array{min: int, max: int, types: string, focus: string}>
     */
    private const TIERS = [
        'A' => ['min' => 12, 'max' => 14, 'types' => '`command` atau `text`', 'focus' => 'recall syntax dan konsep dasar'],
        'B' => ['min' => 10, 'max' => 12, 'types' => '`command` atau `text`', 'focus' => 'kombinasi opsi, audit, dan debugging'],
        'C' => ['min' => 3, 'max' => 3, 'types' => '`scenario`', 'focus' => 'studi kasus troubleshooting multi-langkah'],
    ];

    private const PART_LABELS = ['body' => 'materi', 'A' => 'soal tier A', 'B' => 'soal tier B', 'C' => 'soal tier C'];

    private const OUTLINE_ATTEMPTS = 2;

    public function __construct(
        private ChatClient $client,
        private DocumentReader $reader,
        private ModuleImporter $importer,
    ) {}

    /**
     * @param  Closure(string, int): void|null  $onProgress  called with a status line and the attempt number
     * @return array{markdown: string, errors: list<string>, attempts: int, prompt_tokens: int, completion_tokens: int}
     *
     * @throws RuntimeException when the document cannot be read, the outline fails, or the AI service fails.
     */
    public function generate(Course $board, string $path, string $originalName, ?Closure $onProgress = null): array
    {
        $maxAttempts = max(1, (int) config('services.llm.max_attempts'));
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0];
        $report = function (string $message, int $attempt) use ($onProgress): void {
            if ($onProgress !== null) {
                $onProgress($message, $attempt);
            }
        };

        $system = ['role' => 'system', 'content' => $this->systemPrompt($board)];
        $document = $this->reader->contentParts($path, $originalName);

        $report('Tahap 1/2 · AI membaca dokumen dan menyusun kerangka modul', 1);
        [$meta, $outlineYaml] = $this->outline($board, $system, $document, $usage);

        $conversations = [];

        foreach (array_keys(self::PART_LABELS) as $part) {
            $conversations[$part] = [$system, ['role' => 'user', 'content' => [
                ...$document,
                ['type' => 'text', 'text' => "Kerangka modul (sudah final, jangan diubah):\n\n{$outlineYaml}\n\n".$this->partTask($part, $meta)],
            ]]];
        }

        $outputs = [];
        $truncated = [];
        $pending = array_keys($conversations);
        $markdown = '';
        $errors = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $status = $attempt === 1
                ? 'Tahap 2/2 · menulis materi dan soal secara paralel'
                : 'Memperbaiki '.$this->labels($pending)." yang belum lolos validasi (percobaan {$attempt}/{$maxAttempts})";
            $finished = [];
            $report("{$status} (0/".count($pending).' bagian selesai)', $attempt);

            $results = $this->client->completeMany(
                array_intersect_key($conversations, array_flip($pending)),
                (array) config('services.llm.part_options'),
                function (string $part) use (&$finished, $pending, $status, $attempt, $report): void {
                    $finished[] = self::PART_LABELS[$part];
                    $report("{$status} (".count($finished).'/'.count($pending).' bagian selesai: '.implode(', ', $finished).')', $attempt);
                },
            );

            $failed = array_filter($results, fn ($result): bool => $result instanceof RuntimeException);

            if (count($failed) === count($results)) {
                throw reset($failed);
            }

            foreach ($results as $part => $result) {
                if ($result instanceof RuntimeException) {
                    continue;
                }

                $usage['prompt_tokens'] += $result['prompt_tokens'];
                $usage['completion_tokens'] += $result['completion_tokens'];
                Log::info('AI import part usage', ['part' => $part, 'attempt' => $attempt, 'prompt_tokens' => $result['prompt_tokens'], 'completion_tokens' => $result['completion_tokens'], 'characters' => mb_strlen($result['content'])]);
                $outputs[$part] = $result['content'];
                $truncated[$part] = $result['finish_reason'] === 'length';
                $conversations[$part][] = ['role' => 'assistant', 'content' => $result['content']];
            }

            [$markdown, $partErrors, $globalErrors] = $this->assemble($board, $meta, $outputs, $truncated);

            foreach ($failed as $part => $exception) {
                $partErrors[$part] = ['Permintaan ke AI gagal: '.$exception->getMessage()];
            }

            $errors = [...$globalErrors, ...array_merge(...array_values($partErrors ?: [[]]))];

            if ($errors === []) {
                return ['markdown' => $markdown, 'errors' => [], 'attempts' => $attempt, ...$usage];
            }

            if ($partErrors === []) {
                return $this->fixWholeFile($board, $system, $document, $markdown, $globalErrors, $attempt, $maxAttempts, $usage, $report);
            }

            Log::info('AI import validation errors', ['attempt' => $attempt, 'errors' => $partErrors, 'global' => $globalErrors]);
            $pending = array_keys($partErrors);

            foreach ($partErrors as $part => $messages) {
                if (! isset($failed[$part])) {
                    $conversations[$part][] = ['role' => 'user', 'content' => $this->partFixRequest($part, $messages)];
                }
            }
        }

        return ['markdown' => $markdown, 'errors' => $errors, 'attempts' => $maxAttempts, ...$usage];
    }

    /**
     * @param  array{role: string, content: string}  $system
     * @param  list<array<string, mixed>>  $document
     * @param  array{prompt_tokens: int, completion_tokens: int}  $usage
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function outline(Course $board, array $system, array $document, array &$usage): array
    {
        $messages = [$system, ['role' => 'user', 'content' => [...$document, ['type' => 'text', 'text' => $this->outlineTask()]]]];

        for ($try = 1; ; $try++) {
            $reply = $this->client->complete($messages);
            $usage['prompt_tokens'] += $reply['prompt_tokens'];
            $usage['completion_tokens'] += $reply['completion_tokens'];

            try {
                return $this->normalizeOutline($board, $reply['content']);
            } catch (RuntimeException $exception) {
                if ($try >= self::OUTLINE_ATTEMPTS) {
                    throw new RuntimeException('AI gagal menyusun kerangka modul: '.$exception->getMessage());
                }

                $messages[] = ['role' => 'assistant', 'content' => $reply['content']];
                $messages[] = ['role' => 'user', 'content' => 'Kerangka itu tidak bisa dipakai: '.$exception->getMessage().' Kirim ulang objek JSON kerangka lengkapnya saja.'];
            }
        }
    }

    /**
     * Fields the app decides itself (format, order, version, status, date) are set here, not by the AI.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function normalizeOutline(Course $board, string $content): array
    {
        $json = $this->unwrap($content, '/^\{/m');
        $outline = json_decode(substr($json, 0, (int) strrpos($json, '}') + 1), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('JSON kerangka tidak valid ('.json_last_error_msg().').');
        }

        if (! is_array($outline) || ! is_string($outline['title'] ?? null) || trim($outline['title']) === '') {
            throw new RuntimeException('Kerangka wajib berupa objek JSON dengan `title`.');
        }

        if (! is_array($outline['objectives'] ?? null) || $outline['objectives'] === []) {
            throw new RuntimeException('Kerangka wajib punya `objectives` (list `id` + `text`).');
        }

        $meta = [
            'format' => 1,
            'id' => $this->moduleId($board, is_string($outline['id'] ?? null) ? $outline['id'] : $outline['title']),
            'title' => trim($outline['title']),
            'summary' => is_string($outline['summary'] ?? null) ? trim($outline['summary']) : null,
            'order' => $board->modules()->count() + 1,
            'version' => 1,
            'status' => 'draft',
            'updated' => now()->toDateString(),
            'tested_on' => is_array($outline['tested_on'] ?? null) ? array_values($outline['tested_on']) : [],
            'objectives' => array_values($outline['objectives']),
            'sources' => is_array($outline['sources'] ?? null) ? array_values($outline['sources']) : [],
        ];

        $shared = array_intersect_key($meta, array_flip(['id', 'title', 'summary', 'objectives', 'sources']))
            + ['outline' => is_array($outline['outline'] ?? null) ? $outline['outline'] : []];

        return [$meta, Yaml::dump($shared, 6, 2)];
    }

    /**
     * Module ids are unique across boards; reusing one would turn the import into an update.
     */
    private function moduleId(Course $board, string $candidate): string
    {
        $slug = Str::slug($candidate);
        $slug = str_starts_with($slug, $board->slug.'-') ? $slug : $board->slug.'-'.$slug;
        $id = $slug;

        for ($suffix = 2; Module::query()->where('slug', $id)->exists(); $suffix++) {
            $id = "{$slug}-{$suffix}";
        }

        return $id;
    }

    /**
     * Puts the parts together and runs the validator. Question ids are renumbered per tier, so the
     * parts cannot collide, and each error is attributed to the part its question came from.
     *
     * @param  array<string, mixed>  $meta
     * @param  array<string, string>  $outputs
     * @param  array<string, bool>  $truncated
     * @return array{0: string, 1: array<string, list<string>>, 2: list<string>}
     */
    private function assemble(Course $board, array $meta, array $outputs, array $truncated): array
    {
        $partErrors = [];
        $questions = [];
        $ownerByIndex = [];
        $ownerById = [];

        foreach (array_keys(self::TIERS) as $tier) {
            if (! isset($outputs[$tier])) {
                continue;
            }

            try {
                $list = $this->parseQuestions($outputs[$tier]);
            } catch (RuntimeException $exception) {
                $partErrors[$tier][] = $exception->getMessage();

                continue;
            }

            foreach ($list as $position => $question) {
                if (! is_array($question)) {
                    $partErrors[$tier][] = 'Soal ke-'.($position + 1).' bukan mapping YAML.';

                    continue;
                }

                $id = strtolower($tier).sprintf('%02d', $position + 1);
                $ownerByIndex[count($questions)] = $tier;
                $ownerById[$id] = $tier;
                $questions[] = ['id' => $id, 'tier' => $tier, ...array_diff_key($question, ['id' => true, 'tier' => true])];
            }
        }

        $body = isset($outputs['body']) ? $this->cleanBody($outputs['body']) : '';

        if (isset($outputs['body']) && $body === '') {
            $partErrors['body'][] = 'Materi kosong. Tulis body Markdown materi lengkap.';
        }

        foreach (array_keys(array_filter($truncated)) as $part) {
            $partErrors[$part][] = 'Jawaban terpotong karena melebihi batas output. Tulis lebih ringkas.';
        }

        $markdown = "---\n".Yaml::dump([...$meta, 'questions' => $questions], 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK)."---\n\n{$body}\n";
        $globalErrors = [];

        foreach ($this->importer->analyze($markdown, $board)->report->errors as $error) {
            $owner = match (true) {
                preg_match('/^Soal `([^`]+)`/', $error, $match) === 1 => $ownerById[$match[1]] ?? null,
                preg_match('/^Soal #(\d+)/', $error, $match) === 1 => $ownerByIndex[(int) $match[1] - 1] ?? null,
                default => null,
            };

            if ($owner === null) {
                $globalErrors[] = $error;
            } else {
                $partErrors[$owner][] = $error;
            }
        }

        return [$markdown, $partErrors, $globalErrors];
    }

    /**
     * @return list<mixed>
     */
    private function parseQuestions(string $content): array
    {
        try {
            $parsed = Yaml::parse($this->unwrap($content, '/^questions:/m'));
        } catch (ParseException $exception) {
            throw new RuntimeException('YAML soal tidak valid: '.$exception->getMessage());
        }

        $list = is_array($parsed) && array_key_exists('questions', $parsed) ? $parsed['questions'] : $parsed;

        if (! is_array($list) || ! array_is_list($list) || $list === []) {
            throw new RuntimeException('Jawaban harus berupa YAML yang diawali `questions:` berisi list soal.');
        }

        return $list;
    }

    /**
     * The material from its `# Title` on, without a fence or a frontmatter the AI may have added anyway.
     */
    private function cleanBody(string $content): string
    {
        return $this->unwrap($content, '/^# /m');
    }

    /**
     * The answer from the line matching `$startPattern` on. The model may still put a sentence or an
     * opening fence before it; when a fence opened there, the answer ends at the last line that repeats
     * it, so fences inside the answer (code blocks, YAML literal blocks) stay.
     */
    private function unwrap(string $content, string $startPattern): string
    {
        $text = trim(str_replace("\r\n", "\n", $content));
        $start = preg_match($startPattern, $text, $match, PREG_OFFSET_CAPTURE) === 1 ? $match[0][1] : 0;
        $answer = substr($text, $start);

        if (preg_match('/^(`{3,}|~{3,})[\w-]*[ \t]*$/m', substr($text, 0, $start), $fence) === 1) {
            $closing = strrpos($answer, "\n".$fence[1]);
            $answer = $closing === false ? $answer : substr($answer, 0, $closing);
        }

        return trim($answer);
    }

    /**
     * Fallback for errors outside the questions (for example an invalid source URL in the outline):
     * the assembled file goes back as a whole, the way a manual fix would.
     *
     * @param  array{role: string, content: string}  $system
     * @param  list<array<string, mixed>>  $document
     * @param  list<string>  $errors
     * @param  array{prompt_tokens: int, completion_tokens: int}  $usage
     * @param  Closure(string, int): void  $report
     * @return array{markdown: string, errors: list<string>, attempts: int, prompt_tokens: int, completion_tokens: int}
     */
    private function fixWholeFile(Course $board, array $system, array $document, string $markdown, array $errors, int $attempt, int $maxAttempts, array $usage, Closure $report): array
    {
        while ($attempt < $maxAttempts && $errors !== []) {
            $attempt++;
            $report("Memperbaiki file modul yang belum lolos validasi (percobaan {$attempt}/{$maxAttempts})", $attempt);

            $reply = $this->client->complete([$system, ['role' => 'user', 'content' => [
                ...$document,
                ['type' => 'text', 'text' => "File modul berikut ditolak validator devops-dojo. Perbaiki semua masalah di bawah tanpa mengubah bagian lain yang sudah benar, lalu kirim ulang isi file lengkapnya (mulai dari `---`, tanpa penjelasan).\n\nMasalah:\n- ".implode("\n- ", $errors)."\n\nFile:\n\n{$markdown}"],
            ]]]);

            $usage['prompt_tokens'] += $reply['prompt_tokens'];
            $usage['completion_tokens'] += $reply['completion_tokens'];
            $markdown = $this->extractModule($reply['content']);
            $errors = $this->importer->analyze($markdown, $board)->report->errors;
        }

        return ['markdown' => $markdown, 'errors' => $errors, 'attempts' => $attempt, ...$usage];
    }

    private function outlineTask(): string
    {
        return <<<'TEXT'
            TAHAP 1 — KERANGKA. Baca seluruh dokumen, lalu tulis kerangka modul sebagai SATU objek JSON yang valid (bukan YAML) dengan key berikut saja:
            - `id`: slug topik (huruf kecil, angka, strip)
            - `title`, `summary` (satu kalimat)
            - `tested_on`: array lingkungan pengujian yang disebut dokumen, atau `[]`
            - `objectives`: 4–8 tujuan belajar, tiap item objek `id` (kebab-case) dan `text`
            - `sources`: array objek `title` + `url`, atau `[]` (ikuti aturan sumber)
            - `outline`: array bagian materi, tiap item objek `heading` (judul bagian) dan `points` (array poin isi singkat), termasuk bagian troubleshooting bila dokumen membahasnya

            Materi dan soal ditulis di tahap berikutnya berdasarkan kerangka ini, jadi pastikan `objectives` dan `outline` mencakup semua isi penting dokumen.
            TEXT;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function partTask(string $part, array $meta): string
    {
        if ($part === 'body') {
            return "TAHAP 2 — MATERI. Tulis HANYA body Markdown materi modul ini, dimulai baris `# {$meta['title']}`. "
                .'Setiap item `outline` menjadi bagian `##` dengan judul dan urutan yang sama. Pakai tabel, code block, diagram mermaid, dan callout seperti di template bila membantu. '
                .'Jangan menulis frontmatter dan jangan menulis soal.';
        }

        $tier = self::TIERS[$part];
        $prefix = strtolower($part);
        $count = $tier['min'] === $tier['max'] ? "tepat {$tier['min']}" : "{$tier['min']}–{$tier['max']}";

        return "TAHAP 2 — SOAL TIER {$part}. Tulis HANYA soal tier {$part} ({$tier['focus']}) dalam YAML, dimulai baris `questions:`. "
            ."Buat {$count} soal, type {$tier['types']}, id `{$prefix}01`, `{$prefix}02`, dst., `tier: {$part}`. "
            .'`objective` setiap soal wajib salah satu id di `objectives` kerangka. Soal hanya tentang hal yang dibahas di dokumen dan kerangka. '
            .'Ikuti aturan dan format soal di template persis. Jangan menulis bagian lain.';
    }

    /**
     * @param  list<string>  $errors
     */
    private function partFixRequest(string $part, array $errors): string
    {
        $what = $part === 'body'
            ? 'body Markdown materi lengkapnya'
            : "SEMUA soal tier {$part} lengkap dalam YAML (dimulai baris `questions:`; id `".strtolower($part).'01` = soal pertama, dst.)';

        return "Hasilmu ditolak validator devops-dojo. Perbaiki semua masalah berikut tanpa mengubah bagian lain yang sudah benar, lalu kirim ulang {$what}, tanpa penjelasan:\n- "
            .implode("\n- ", $errors);
    }

    /**
     * @param  list<string>  $parts
     */
    private function labels(array $parts): string
    {
        return implode(', ', array_map(fn (string $part): string => self::PART_LABELS[$part], $parts));
    }

    private function systemPrompt(Course $board): string
    {
        return view('ai-imports.prompt', [
            'board' => $board,
            'existingIds' => Module::query()->orderBy('slug')->pluck('slug')->all(),
            'template' => str_replace('__BOARD__', $board->slug, File::get(resource_path('templates/module-template.md'))),
        ])->render();
    }

    /**
     * The model is told to answer with the bare file, but may still wrap it in a fence or add a
     * sentence around it. The module body has fences of its own, so the outer fence is the last
     * line that repeats the opening one.
     */
    public function extractModule(string $content): string
    {
        $text = trim(str_replace("\r\n", "\n", $content));
        $start = strpos("\n".$text, "\n---\n");

        if ($start === false) {
            return $text."\n";
        }

        $before = substr($text, 0, $start);
        $module = substr($text, $start);

        if (preg_match('/^(`{3,}|~{3,})[\w-]*[ \t]*$/m', $before, $match) === 1) {
            $closing = strrpos($module, "\n".$match[1]);

            if ($closing !== false) {
                $module = substr($module, 0, $closing);
            }
        }

        return rtrim($module)."\n";
    }
}
