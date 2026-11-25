<?php

namespace App\Services\Content;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A validated module file waiting between preview and confirm. The file sits in private storage;
 * only the session that stored it knows its token, so only that session may confirm it.
 */
class PendingImports
{
    private const DIRECTORY = 'imports';

    public function put(Request $request, Course $board, string $raw): string
    {
        $this->pruneStale();

        $token = (string) Str::uuid();
        Storage::disk('local')->put($this->path($token), $raw);
        $request->session()->put($this->sessionKey($token), $board->id);

        return $token;
    }

    /**
     * @return array{0: string|null, 1: Course|null}
     */
    public function get(Request $request, string $token): array
    {
        $boardId = $request->session()->get($this->sessionKey($token));
        $board = $boardId === null ? null : Course::query()->find($boardId);
        $raw = $board === null ? null : Storage::disk('local')->get($this->path($token));

        return [$raw, $board];
    }

    public function forget(Request $request, string $token): void
    {
        Storage::disk('local')->delete($this->path($token));
        $request->session()->forget($this->sessionKey($token));
    }

    private function path(string $token): string
    {
        return self::DIRECTORY.'/'.$token.'.md';
    }

    private function sessionKey(string $token): string
    {
        return 'pending_imports.'.$token;
    }

    private function pruneStale(): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDay()->getTimestamp();

        foreach ($disk->files(self::DIRECTORY) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
