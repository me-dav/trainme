<?php

namespace Tests\Feature;

use App\Models\Attempt;
use App\Models\Course;
use App\Models\Module;
use App\Models\Question;
use App\Models\User;
use App\Services\Content\ModuleImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleImportTest extends TestCase
{
    use RefreshDatabase;

    private const A02_BLOCK = "  - id: a02\n    tier: A\n    type: text\n    objective: chmod\n    prompt: Oktal dari `rwxr-x---`?\n    answers: ['750']\n    source: https://man7.org/linux/man-pages/man1/chmod.1.html\n";

    private function fixture(): string
    {
        return File::get(base_path('tests/Fixtures/mini-module.md'));
    }

    private function importer(): ModuleImporter
    {
        return $this->app->make(ModuleImporter::class);
    }

    public function test_import_creates_course_module_and_questions(): void
    {
        $module = $this->importer()->import($this->fixture());

        $this->assertSame('mini-module', $module->slug);
        $this->assertSame('test-course', $module->course->slug);
        $this->assertSame(['A' => 2, 'B' => 1, 'C' => 1], $module->quiz_sizes);
        $this->assertSame(['a01', 'a02', 'b01', 'c01'], $module->questions()->pluck('key')->all());
        $this->assertStringContainsString('## Bagian satu', $module->body);
    }

    public function test_reimporting_the_same_file_changes_nothing(): void
    {
        $this->importer()->import($this->fixture());
        $analysis = $this->importer()->analyze($this->fixture());

        $this->assertFalse($analysis->hasChanges());
        $this->assertSame(['a01', 'a02', 'b01', 'c01'], $analysis->diff['unchanged']);
        $this->assertSame(1, Course::query()->count());
    }

    public function test_reimport_updates_changed_questions_and_archives_removed_ones_keeping_history(): void
    {
        $module = $this->importer()->import($this->fixture());
        $removed = $module->questions()->where('key', 'a02')->firstOrFail();

        $attempt = Attempt::factory()->for(User::factory())->for($module)->create(['question_ids' => [$removed->id]]);
        $attempt->answers()->create(['question_id' => $removed->id, 'position' => 0, 'answer' => '750', 'score' => 1, 'is_correct' => true]);

        $updated = str_replace(
            ['version: 1', 'Tambahkan x untuk owner', self::A02_BLOCK],
            ['version: 2', 'Tambahkan izin eksekusi untuk owner', ''],
            $this->fixture(),
        );

        $analysis = $this->importer()->analyze($updated);
        $this->assertSame(['a01'], $analysis->diff['changed']);
        $this->assertSame(['a02'], $analysis->diff['removed']);

        $module = $this->importer()->import($updated);

        $this->assertSame(2, $module->version);
        $this->assertSame(['a01', 'b01', 'c01'], $module->questions()->pluck('key')->all());
        $this->assertSoftDeleted($removed);
        $this->assertSame($removed->id, $attempt->answers()->first()->question->id);
    }

    public function test_bringing_back_an_archived_question_restores_it(): void
    {
        $this->importer()->import($this->fixture());
        $this->importer()->import(str_replace(self::A02_BLOCK, '', $this->fixture()));
        $this->importer()->import($this->fixture());

        $this->assertSame(4, Question::query()->count());
        $this->assertSame(4, Question::withTrashed()->count());
    }

    public function test_changed_content_without_a_version_bump_warns(): void
    {
        $this->importer()->import($this->fixture());

        $analysis = $this->importer()->analyze(str_replace('Teks materi.', 'Teks materi baru.', $this->fixture()));

        $this->assertTrue($analysis->diff['module_changed']);
        $this->assertStringContainsString('`version` tidak naik', implode("\n", $analysis->report->warnings));
    }

    public function test_artisan_command_imports_the_content_directory(): void
    {
        $this->artisan('dojo:import')->assertSuccessful();

        $module = Module::query()->where('slug', 'linux-permission-ownership')->firstOrFail();
        $this->assertSame('linux', $module->course->slug);
        $this->assertGreaterThanOrEqual(30, $module->questions()->count());

        $this->artisan('dojo:import')->expectsOutputToContain('tidak ada perubahan')->assertSuccessful();
    }

    public function test_artisan_dry_run_does_not_save(): void
    {
        $this->artisan('dojo:import', ['path' => base_path('tests/Fixtures/mini-module.md'), '--dry-run' => true])
            ->expectsOutputToContain('dry run')
            ->assertSuccessful();

        $this->assertSame(0, Module::query()->count());
    }
}
