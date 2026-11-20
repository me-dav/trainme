<?php

namespace App\Console\Commands;

use App\Services\Content\ModuleImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('dojo:import {path? : Module file or directory (default: the content directory)} {--dry-run : Validate and show the diff without saving}')]
#[Description('Validate and import module files (Markdown + YAML frontmatter)')]
class ImportModuleCommand extends Command
{
    public function handle(ModuleImporter $importer): int
    {
        $path = $this->argument('path') ?? config('dojo.content_path');
        $files = $this->moduleFiles($path);

        if ($files === []) {
            $this->components->error("Tidak ada file modul (.md) di {$path}.");

            return self::FAILURE;
        }

        $failed = 0;

        foreach ($files as $file) {
            $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file);
            $raw = File::get($file);
            $analysis = $importer->analyze($raw);

            if (! $analysis->isValid()) {
                $failed++;
                $this->components->error("{$relative}: tidak valid");
                $this->components->bulletList($analysis->report->errors);

                continue;
            }

            foreach ($analysis->report->warnings as $warning) {
                $this->components->warn("{$relative}: {$warning}");
            }

            $diff = $analysis->diff;
            $summary = sprintf(
                '%s — baru %d, berubah %d, diarsipkan %d, tetap %d',
                $diff['is_new_module'] ? 'modul baru' : 'update v'.$diff['previous_version'].' → v'.$analysis->report->definition['version'],
                count($diff['new']),
                count($diff['changed']),
                count($diff['removed']),
                count($diff['unchanged']),
            );

            if ($this->option('dry-run')) {
                $this->components->info("{$relative}: valid ({$summary}) — dry run, tidak disimpan.");

                continue;
            }

            if (! $analysis->hasChanges()) {
                $this->components->info("{$relative}: tidak ada perubahan.");

                continue;
            }

            $module = $importer->import($raw);
            $this->components->info("{$relative}: diimpor sebagai \"{$module->title}\" ({$summary}).");
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function moduleFiles(string $path): array
    {
        if (File::isFile($path)) {
            return [$path];
        }

        if (! File::isDirectory($path)) {
            return [];
        }

        $files = collect(File::allFiles($path))
            ->filter(fn ($file): bool => $file->getExtension() === 'md' && strtoupper($file->getFilenameWithoutExtension()) !== 'FORMAT'
                && strtoupper($file->getFilenameWithoutExtension()) !== 'README')
            ->map(fn ($file): string => $file->getPathname())
            ->sort()
            ->values()
            ->all();

        return $files;
    }
}
