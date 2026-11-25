<?php

namespace Tests\Feature;

use App\Enums\AiImportStatus;
use App\Jobs\GenerateModuleFromDocument;
use App\Models\AiImport;
use App\Models\Course;
use App\Models\Module;
use App\Models\User;
use App\Services\Ai\DocumentReader;
use App\Services\Ai\ModuleGenerator;
use App\Services\Content\ModuleParser;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;
use ZipArchive;

class AiImportTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://llm.test/v1/chat/completions';

    private Course $board;

    private User $user;

    /**
     * Requests the fake AI received, per task (outline, body, A, B, C, whole).
     *
     * @var array<string, int>
     */
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        config([
            'services.llm.base_url' => 'https://llm.test/v1',
            'services.llm.key' => 'test-key',
            'services.llm.max_attempts' => 3,
        ]);

        $this->board = Course::factory()->create(['slug' => 'linux', 'title' => 'Linux']);
        $this->user = User::factory()->create();
    }

    private function moduleFile(): string
    {
        return File::get(base_path('tests/Fixtures/mini-module.md'));
    }

    /**
     * @return array{meta: array<string, mixed>, body: string}
     */
    private function fixture(): array
    {
        return app(ModuleParser::class)->parse($this->moduleFile());
    }

    private function outlineAnswer(?array $sources = null): string
    {
        $meta = $this->fixture()['meta'];

        return 'Berikut kerangkanya:
```json
'.json_encode([
            'id' => 'mini-module',
            'title' => $meta['title'],
            'summary' => $meta['summary'],
            'tested_on' => [],
            'objectives' => $meta['objectives'],
            'sources' => $sources ?? $meta['sources'],
            'outline' => [['heading' => 'Bagian satu', 'points' => ['`chmod` dasar']]],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).'
```';
    }

    /**
     * @param  (callable(array<string, mixed>): array<string, mixed>)|null  $change
     */
    private function questionsAnswer(string $tier, ?callable $change = null): string
    {
        $questions = collect($this->fixture()['meta']['questions'])
            ->where('tier', $tier)
            ->map(fn (array $question): array => $change === null ? $question : $change($question))
            ->values()
            ->all();

        return "Berikut soalnya:\n```yaml\nquestions:\n".Yaml::dump($questions, 10, 2, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK).'```';
    }

    private function answerFor(string $task): string
    {
        return match ($task) {
            'outline' => $this->outlineAnswer(),
            'body' => $this->fixture()['body'],
            'whole' => $this->moduleFile(),
            default => $this->questionsAnswer($task),
        };
    }

    /**
     * A streamed answer: the content arrives in several `data:` chunks, usage in the last one.
     */
    private function streamed(string $content, string $finishReason = 'stop'): PromiseInterface
    {
        $events = collect(str_split($content, 400))
            ->map(fn (string $piece): array => ['choices' => [['delta' => ['content' => $piece], 'finish_reason' => null]]])
            ->push(['choices' => [['delta' => [], 'finish_reason' => $finishReason]]])
            ->push(['choices' => [], 'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 500]])
            ->map(fn (array $event): string => 'data: '.json_encode($event)."\n\n")
            ->implode('');

        return Http::response($events."data: [DONE]\n\n", 200, ['Content-Type' => 'text/event-stream']);
    }

    private function taskOf(Request $request): string
    {
        $text = collect($request['messages'][1]['content'])->where('type', 'text')->pluck('text')->implode("\n");

        return match (true) {
            str_contains($text, 'File modul berikut ditolak') => 'whole',
            str_contains($text, 'TAHAP 1') => 'outline',
            str_contains($text, 'TAHAP 2 — MATERI') => 'body',
            preg_match('/SOAL TIER ([ABC])/', $text, $match) === 1 => $match[1],
            default => 'unknown',
        };
    }

    /**
     * Answers each request according to its task. An override is a sequence: the n-th request for
     * that task gets the n-th entry (the last entry repeats).
     *
     * @param  array<string, list<string|PromiseInterface>>  $overrides
     */
    private function fakeAi(array $overrides = []): void
    {
        $this->calls = [];

        Http::fake([self::ENDPOINT => function (Request $request) use ($overrides) {
            $task = $this->taskOf($request);
            $call = $this->calls[$task] = ($this->calls[$task] ?? 0) + 1;
            $answer = isset($overrides[$task]) ? $overrides[$task][min($call, count($overrides[$task])) - 1] : $this->answerFor($task);

            return $answer instanceof PromiseInterface ? $answer : $this->streamed($answer);
        }]);
    }

    private function importPdf(): AiImport
    {
        $this->actingAs($this->user)->post(route('ai-imports.store', $this->board), [
            'document' => UploadedFile::fake()->createWithContent('bab-3.pdf', "%PDF-1.4\n% test document\n%%EOF\n"),
        ]);

        return AiImport::query()->sole();
    }

    public function test_ai_import_is_offered_only_when_configured(): void
    {
        $this->actingAs($this->user)->get(route('boards.show', $this->board))
            ->assertOk()
            ->assertSee('Impor dari PDF/Word')
            ->assertSee('+ Impor modul');

        config(['services.llm.key' => null]);

        $this->get(route('boards.show', $this->board))
            ->assertOk()
            ->assertDontSee('Impor dari PDF/Word')
            ->assertSee('+ Impor modul');
    }

    public function test_uploading_a_document_queues_generation_and_shows_progress(): void
    {
        Queue::fake();

        $aiImport = $this->importPdf();

        $this->assertSame(AiImportStatus::Pending, $aiImport->status);
        $this->assertSame('bab-3.pdf', $aiImport->original_name);
        Storage::disk('local')->assertExists($aiImport->source_path);
        Queue::assertPushed(GenerateModuleFromDocument::class, fn (GenerateModuleFromDocument $job): bool => $job->aiImport->is($aiImport));

        $this->get(route('ai-imports.show', $aiImport))
            ->assertOk()
            ->assertSee('Menunggu giliran diproses')
            ->assertSee('http-equiv="refresh"', false);
    }

    public function test_only_pdf_and_word_documents_are_accepted(): void
    {
        $this->actingAs($this->user)
            ->from(route('boards.show', $this->board))
            ->post(route('ai-imports.store', $this->board), ['document' => UploadedFile::fake()->createWithContent('catatan.txt', 'halo')])
            ->assertRedirect(route('boards.show', $this->board))
            ->assertSessionHasErrors(['document' => 'File harus berupa PDF atau Word (.docx).'], errorBag: 'aiImport');

        $this->assertSame(0, AiImport::query()->count());
    }

    public function test_outline_then_four_parallel_parts_become_one_draft_module(): void
    {
        $this->fakeAi();

        $aiImport = $this->importPdf();

        $this->assertSame(AiImportStatus::Ready, $aiImport->status);
        $this->assertSame(1, $aiImport->attempts);
        $this->assertSame(['A' => 1, 'B' => 1, 'C' => 1, 'body' => 1, 'outline' => 1], collect($this->calls)->sortKeys()->all());
        $this->assertSame(5000, $aiImport->prompt_tokens);
        $this->assertSame(2500, $aiImport->completion_tokens);
        Storage::disk('local')->assertMissing($aiImport->source_path);

        $definition = $this->fixture();
        $this->assertStringContainsString('id: linux-mini-module', $aiImport->result);
        $this->assertStringContainsString($definition['body'], $aiImport->result);

        Http::assertSent(function (Request $request): bool {
            $content = $request['messages'][1]['content'];

            return $request->hasHeader('Authorization', 'Bearer test-key')
                && $request['stream'] === true
                && str_contains($request['messages'][0]['content'], '`id` modul diawali `linux-`')
                && $content[0]['type'] === 'file'
                && str_starts_with($content[0]['file']['file_data'], 'data:application/pdf;base64,')
                && str_contains($content[1]['text'], 'SOAL TIER B')
                && str_contains($content[1]['text'], 'id: linux-mini-module');
        });

        // Thinking stays on for the outline and is turned down for the parallel parts, where it costs the most time.
        Http::assertSent(fn (Request $request): bool => $this->taskOf($request) === 'outline' && ! isset($request['reasoning_effort']));
        Http::assertSent(fn (Request $request): bool => $this->taskOf($request) === 'B' && $request['reasoning_effort'] === 'low');

        $previewUrl = $this->get(route('ai-imports.show', $aiImport))->assertRedirect()->headers->get('Location');

        $this->get($previewUrl)->assertOk()->assertSee('Mini Module')->assertSee('disimpan sebagai draft');
        $this->post($previewUrl)->assertRedirect(route('modules.show', 'linux-mini-module'));

        $module = Module::query()->sole();
        $this->assertSame($this->board->id, $module->course_id);
        $this->assertSame('draft', $module->status);
        $this->assertSame(['a01', 'a02', 'b01', 'c01'], $module->questions()->orderBy('position')->pluck('key')->all());
    }

    public function test_only_the_part_with_errors_is_rewritten(): void
    {
        $brokenB = $this->questionsAnswer('B', fn (array $question): array => [...$question, 'tests' => ['pass' => ['ls -l /srv']]]);
        $this->fakeAi(['B' => [$brokenB, $this->questionsAnswer('B')]]);

        $aiImport = $this->importPdf();

        $this->assertSame(AiImportStatus::Ready, $aiImport->status);
        $this->assertSame(2, $aiImport->attempts);
        $this->assertSame(2, $this->calls['B']);
        $this->assertSame(1, $this->calls['A']);
        $this->assertSame(1, $this->calls['C']);
        $this->assertSame(1, $this->calls['body']);

        Http::assertSent(function (Request $request): bool {
            $last = collect($request['messages'])->last();

            return $last['role'] === 'user'
                && is_string($last['content'])
                && str_contains($last['content'], 'ditolak validator')
                && str_contains($last['content'], 'Soal `b01`: self-test gagal')
                && str_contains($last['content'], 'SEMUA soal tier B');
        });
    }

    public function test_it_gives_up_after_the_last_attempt_and_shows_what_the_ai_wrote(): void
    {
        $this->fakeAi(['C' => [$this->questionsAnswer('C', fn (array $question): array => [...$question, 'objective' => 'tidak-ada'])]]);

        $aiImport = $this->importPdf();

        $this->assertSame(AiImportStatus::Failed, $aiImport->status);
        $this->assertSame(3, $aiImport->attempts);
        $this->assertSame(3, $this->calls['C']);
        $this->assertSame(1, $this->calls['A']);

        $this->get(route('ai-imports.show', $aiImport))
            ->assertOk()
            ->assertSee('Impor gagal')
            ->assertSee('belum lolos validasi setelah 3 percobaan')
            ->assertSee('objective `tidak-ada` tidak ada di daftar `objectives`', false)
            ->assertSee('Hasil terakhir dari AI')
            ->assertDontSee('http-equiv="refresh"', false);
        $this->assertSame(0, Module::query()->count());
    }

    public function test_errors_outside_the_questions_fall_back_to_fixing_the_whole_file(): void
    {
        $this->fakeAi(['outline' => [$this->outlineAnswer([['title' => 'Catatan', 'url' => 'bukan-url']])]]);

        $aiImport = $this->importPdf();

        $this->assertSame(AiImportStatus::Ready, $aiImport->status);
        $this->assertSame(2, $aiImport->attempts);
        $this->assertSame(1, $this->calls['whole']);
        Http::assertSent(fn (Request $request): bool => $this->taskOf($request) === 'whole'
            && str_contains($request['messages'][1]['content'][1]['text'], 'sources[0]'));
    }

    public function test_an_unusable_outline_is_retried_once_then_fails(): void
    {
        $this->fakeAi(['outline' => ['{"title": "belum selesai"']]);

        $aiImport = $this->importPdf();

        $this->assertSame(AiImportStatus::Failed, $aiImport->status);
        $this->assertSame(2, $this->calls['outline']);
        $this->assertStringContainsString('AI gagal menyusun kerangka modul', $aiImport->error);
        $this->assertArrayNotHasKey('A', $this->calls);
    }

    public function test_a_failed_part_request_is_retried_without_rewriting_the_others(): void
    {
        $this->fakeAi(['A' => [Http::response(['error' => ['message' => 'busy']], 503), $this->questionsAnswer('A')]]);

        $aiImport = $this->importPdf();

        $this->assertSame(AiImportStatus::Ready, $aiImport->status);
        $this->assertSame(2, $this->calls['A']);
        $this->assertSame(1, $this->calls['B']);
    }

    public function test_an_ai_service_error_fails_the_import_with_its_message(): void
    {
        $this->fakeAi(['outline' => [Http::response(['error' => ['message' => 'Insufficient balance']], 402)]]);

        $aiImport = $this->importPdf();

        $this->assertSame(AiImportStatus::Failed, $aiImport->status);
        $this->assertStringContainsString('HTTP 402', $aiImport->error);
        $this->assertStringContainsString('Insufficient balance', $aiImport->error);
        Storage::disk('local')->assertMissing($aiImport->source_path);
    }

    public function test_an_error_inside_the_stream_fails_the_import(): void
    {
        $this->fakeAi(['outline' => [Http::response(
            'data: '.json_encode(['error' => ['message' => 'upstream overloaded']])."\n\n",
            200,
            ['Content-Type' => 'text/event-stream'],
        )]]);

        $aiImport = $this->importPdf();

        $this->assertSame(AiImportStatus::Failed, $aiImport->status);
        $this->assertStringContainsString('upstream overloaded', $aiImport->error);
    }

    public function test_a_truncated_part_is_reported(): void
    {
        config(['services.llm.max_attempts' => 1]);
        $this->fakeAi(['B' => [$this->streamed($this->questionsAnswer('B'), 'length')]]);

        $aiImport = $this->importPdf();

        $this->assertSame(AiImportStatus::Failed, $aiImport->status);
        $this->assertStringContainsString('terpotong', implode(' ', $aiImport->validation_errors));
    }

    public function test_progress_page_shows_the_current_stage(): void
    {
        $aiImport = AiImport::factory()->for($this->user)->for($this->board, 'course')->create([
            'status' => AiImportStatus::Processing,
            'attempts' => 1,
            'progress' => 'Tahap 2/2 · menulis materi dan soal secara paralel (2/4 bagian selesai)',
            'started_at' => now(),
        ]);

        $this->actingAs($this->user)->get(route('ai-imports.show', $aiImport))
            ->assertOk()
            ->assertSee('Tahap 2/2 · menulis materi dan soal secara paralel (2/4 bagian selesai)');
    }

    public function test_other_users_cannot_open_someone_elses_import(): void
    {
        $aiImport = AiImport::factory()->for($this->user)->for($this->board, 'course')->create();

        $this->actingAs(User::factory()->create())
            ->get(route('ai-imports.show', $aiImport))
            ->assertNotFound();
    }

    public function test_word_documents_are_sent_as_markdown(): void
    {
        $path = Storage::disk('local')->path('lab.docx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('word/document.xml', <<<'XML'
            <?xml version="1.0" encoding="UTF-8" standalone="yes"?>
            <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>
              <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Volume Docker</w:t></w:r></w:p>
              <w:p><w:pPr><w:tabs><w:tab w:val="left" w:pos="720"/></w:tabs></w:pPr><w:r><w:t xml:space="preserve">Volume </w:t></w:r><w:r><w:t>tetap ada.</w:t></w:r></w:p>
              <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr><w:r><w:t>docker volume ls</w:t></w:r></w:p>
              <w:tbl>
                <w:tr><w:tc><w:p><w:r><w:t>Perintah</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Arti</w:t></w:r></w:p></w:tc></w:tr>
                <w:tr><w:tc><w:p><w:r><w:t>prune</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>hapus a|b</w:t></w:r></w:p></w:tc></w:tr>
              </w:tbl>
            </w:body></w:document>
            XML);
        $zip->close();

        $this->assertSame(
            "# Volume Docker\n\nVolume tetap ada.\n\n- docker volume ls\n\n| Perintah | Arti |\n| --- | --- |\n| prune | hapus a\\|b |",
            app(DocumentReader::class)->docxToMarkdown($path),
        );

        $this->fakeAi();

        $this->actingAs($this->user)->post(route('ai-imports.store', $this->board), [
            'document' => new UploadedFile($path, 'lab.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true),
        ]);

        $this->assertSame(AiImportStatus::Ready, AiImport::query()->sole()->status);
        Http::assertSent(fn (Request $request): bool => $request['messages'][1]['content'][0]['type'] === 'text'
            && str_contains($request['messages'][1]['content'][0]['text'], '# Volume Docker'));
    }

    public function test_the_outer_fence_is_removed_but_fences_inside_the_module_are_kept(): void
    {
        $module = "---\nformat: 1\n---\n\n# Judul\n\n```bash\nls -la\n```\n";

        $extracted = app(ModuleGenerator::class)->extractModule("Ini hasilnya:\n```markdown\n{$module}```\nSelesai.");

        $this->assertSame($module, $extracted);
        $this->assertSame($module, app(ModuleGenerator::class)->extractModule($module));
    }
}
