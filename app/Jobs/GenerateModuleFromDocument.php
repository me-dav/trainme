<?php

namespace App\Jobs;

use App\Enums\AiImportStatus;
use App\Models\AiImport;
use App\Services\Ai\ModuleGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Generates a module file from an uploaded document in the background. Not retried by the
 * queue: the generator already retries on validation errors, and a second run would bill again.
 */
class GenerateModuleFromDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * The import is gone when its board was deleted before the job ran.
     */
    public bool $deleteWhenMissingModels = true;

    /**
     * Seconds the job may run: every attempt can take up to the HTTP timeout. The queue's
     * `retry_after` must be longer than this, or a second worker picks the job up mid-run.
     */
    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(public AiImport $aiImport)
    {
        $this->timeout = (int) config('services.llm.timeout') * max(1, (int) config('services.llm.max_attempts')) + 60;
    }

    public function handle(ModuleGenerator $generator): void
    {
        $aiImport = $this->aiImport;
        $disk = Storage::disk('local');

        $aiImport->update(['status' => AiImportStatus::Processing, 'started_at' => now(), 'error' => null, 'progress' => null]);

        try {
            if (! $disk->exists($aiImport->source_path)) {
                throw new RuntimeException('File dokumen tidak ditemukan lagi di server. Unggah ulang.');
            }

            $result = $generator->generate(
                $aiImport->course,
                $disk->path($aiImport->source_path),
                $aiImport->original_name,
                fn (string $progress, int $attempt) => $aiImport->update(['progress' => $progress, 'attempts' => $attempt]),
            );
        } catch (RuntimeException $exception) {
            $this->markFailed($exception->getMessage());

            return;
        }

        $isValid = $result['errors'] === [];

        $aiImport->update([
            'status' => $isValid ? AiImportStatus::Ready : AiImportStatus::Failed,
            'attempts' => $result['attempts'],
            'result' => $result['markdown'],
            'error' => $isValid ? null : "Hasil AI masih belum lolos validasi setelah {$result['attempts']} percobaan.",
            'validation_errors' => $isValid ? null : $result['errors'],
            'prompt_tokens' => $result['prompt_tokens'],
            'completion_tokens' => $result['completion_tokens'],
            'finished_at' => now(),
        ]);

        $disk->delete($aiImport->source_path);
    }

    /**
     * Unexpected errors and timeouts end here, so the progress page never waits forever.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('AI import failed', ['ai_import' => $this->aiImport->id, 'exception' => $exception?->getMessage()]);

        $this->markFailed('Proses AI berhenti karena kesalahan tak terduga atau melebihi batas waktu. Coba unggah ulang.');
    }

    private function markFailed(string $message): void
    {
        $this->aiImport->update([
            'status' => AiImportStatus::Failed,
            'error' => $message,
            'finished_at' => now(),
        ]);

        Storage::disk('local')->delete($this->aiImport->source_path);
    }
}
