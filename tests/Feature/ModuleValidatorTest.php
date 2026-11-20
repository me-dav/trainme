<?php

namespace Tests\Feature;

use App\Services\Content\ModuleImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleValidatorTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return File::get(base_path('tests/Fixtures/mini-module.md'));
    }

    private function errorsFor(string $raw): string
    {
        return implode("\n", $this->app->make(ModuleImporter::class)->analyze($raw)->report->errors);
    }

    public function test_fixture_module_is_valid(): void
    {
        $analysis = $this->app->make(ModuleImporter::class)->analyze($this->fixture());

        $this->assertSame([], $analysis->report->errors);
        $this->assertCount(4, $analysis->report->definition['questions']);
    }

    public function test_every_module_in_the_content_directory_is_valid(): void
    {
        $files = collect(File::allFiles(base_path('content')))
            ->filter(fn ($file): bool => $file->getExtension() === 'md' && $file->getFilename() !== 'FORMAT.md');

        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $report = $this->app->make(ModuleImporter::class)->analyze($file->getContents())->report;

            $this->assertSame([], $report->errors, $file->getRelativePathname().' memiliki error');
        }
    }

    public function test_missing_frontmatter_is_reported(): void
    {
        $this->assertStringContainsString('frontmatter', $this->errorsFor("# Hanya markdown\n"));
    }

    public function test_invalid_yaml_reports_the_problem(): void
    {
        $this->assertStringContainsString('YAML frontmatter tidak valid', $this->errorsFor("---\nformat: 1\nid: [tidak ditutup\n---\nbody"));
    }

    public function test_self_test_catches_a_wrong_answer_key(): void
    {
        $raw = str_replace("fail: ['chmod +x deploy.sh']", "fail: ['chmod u+x deploy.sh']", $this->fixture());

        $this->assertStringContainsString('seharusnya SALAH dinilai benar', $this->errorsFor($raw));
    }

    public function test_self_test_catches_a_scenario_example_that_does_not_pass(): void
    {
        $raw = str_replace("          namei -l /srv/app/storage\n          chgrp", "          ls /srv/app/storage\n          chgrp", $this->fixture());

        $this->assertStringContainsString('seharusnya BENAR dinilai salah', $this->errorsFor($raw));
    }

    public function test_numeric_answers_must_be_quoted(): void
    {
        $raw = str_replace("answers: ['750']", 'answers: [750]', $this->fixture());

        $this->assertStringContainsString('tanda kutip', $this->errorsFor($raw));
    }

    public function test_invalid_regex_is_reported(): void
    {
        $raw = str_replace("patterns: ['namei\\s+-l']", "patterns: ['namei(']", $this->fixture());

        $this->assertStringContainsString('regex tidak valid', $this->errorsFor($raw));
    }

    public function test_duplicate_question_ids_are_reported(): void
    {
        $raw = str_replace('  - id: a02', '  - id: a01', $this->fixture());

        $this->assertStringContainsString('dipakai lebih dari sekali', $this->errorsFor($raw));
    }

    public function test_unknown_objective_is_reported(): void
    {
        $raw = preg_replace('/objective: chmod/', 'objective: missing', $this->fixture(), 1);

        $this->assertStringContainsString('objective `missing`', $this->errorsFor($raw));
    }

    public function test_scenario_requires_a_passing_example(): void
    {
        $raw = str_replace(
            "    tests:\n      pass:\n        - |\n          namei -l /srv/app/storage\n          chgrp -R www-data /srv/app/storage\n",
            "    tests:\n",
            $this->fixture(),
        );

        $this->assertStringContainsString('minimal satu `tests.pass`', $this->errorsFor($raw));
    }

    public function test_small_question_banks_produce_a_warning_not_an_error(): void
    {
        $report = $this->app->make(ModuleImporter::class)->analyze(str_replace('  A: 2', '  A: 5', $this->fixture()))->report;

        $this->assertSame([], $report->errors);
        $this->assertStringContainsString('hanya 2 soal', implode("\n", $report->warnings));
    }
}
