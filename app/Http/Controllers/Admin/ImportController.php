<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Content\ModuleImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Upload into a board → validate → preview diff → confirm. The uploaded file waits in
 * private storage between preview and confirm; only the uploader's session may confirm it.
 */
class ImportController extends Controller
{
    private const PENDING_DIR = 'imports';

    public function __construct(private ModuleImporter $importer) {}

    public function store(Request $request, Course $board): RedirectResponse
    {
        $request->validateWithBag('import', [
            'file' => ['nullable', 'required_without:content', 'file', 'max:1024', 'extensions:md,markdown'],
            'content' => ['nullable', 'required_without:file', 'string', 'max:1000000'],
        ], [
            'file.required_without' => 'Pilih file modul atau tempel isinya.',
            'content.required_without' => 'Pilih file modul atau tempel isinya.',
            'file.extensions' => 'File harus berekstensi .md.',
            'file.max' => 'Ukuran file maksimal 1 MB.',
        ]);

        $raw = $request->hasFile('file')
            ? (string) $request->file('file')->get()
            : (string) $request->input('content');

        $analysis = $this->importer->analyze($raw, $board);

        if (! $analysis->isValid()) {
            return redirect()->route('boards.show', $board)
                ->withInput($request->hasFile('file') ? [] : ['content' => $raw])
                ->with('importErrors', $analysis->report->errors)
                ->with('importWarnings', $analysis->report->warnings);
        }

        $this->pruneStalePending();

        $token = (string) Str::uuid();
        Storage::disk('local')->put($this->pendingPath($token), $raw);
        $request->session()->put($this->sessionKey($token), $board->id);

        return redirect()->route('import.show', $token);
    }

    public function show(Request $request, string $token): View|RedirectResponse
    {
        [$raw, $board] = $this->pending($request, $token);

        if ($raw === null) {
            return $this->expired();
        }

        return view('admin.import.preview', [
            'token' => $token,
            'board' => $board,
            'analysis' => $this->importer->analyze($raw, $board),
        ]);
    }

    public function confirm(Request $request, string $token): RedirectResponse
    {
        [$raw, $board] = $this->pending($request, $token);

        if ($raw === null) {
            return $this->expired();
        }

        if (! $this->importer->analyze($raw, $board)->isValid()) {
            return redirect()->route('import.show', $token);
        }

        $module = $this->importer->import($raw, $board);

        Storage::disk('local')->delete($this->pendingPath($token));
        $request->session()->forget($this->sessionKey($token));

        return redirect()->route('modules.show', $module)->with('status', "Modul \"{$module->title}\" v{$module->version} berhasil diimpor ke board {$board->title}.");
    }

    /**
     * @return array{0: string|null, 1: Course|null}
     */
    private function pending(Request $request, string $token): array
    {
        $boardId = $request->session()->get($this->sessionKey($token));
        $board = $boardId === null ? null : Course::query()->find($boardId);
        $raw = $board === null ? null : Storage::disk('local')->get($this->pendingPath($token));

        return [$raw, $board];
    }

    private function expired(): RedirectResponse
    {
        return redirect()->route('dashboard')->with('error', 'Sesi impor tidak ditemukan atau sudah kedaluwarsa. Unggah ulang file-nya dari board.');
    }

    private function pendingPath(string $token): string
    {
        return self::PENDING_DIR.'/'.$token.'.md';
    }

    private function sessionKey(string $token): string
    {
        return 'pending_imports.'.$token;
    }

    private function pruneStalePending(): void
    {
        $disk = Storage::disk('local');
        $cutoff = now()->subDay()->getTimestamp();

        foreach ($disk->files(self::PENDING_DIR) as $file) {
            if ($disk->lastModified($file) < $cutoff) {
                $disk->delete($file);
            }
        }
    }
}
