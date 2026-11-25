<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Services\Content\ModuleImporter;
use App\Services\Content\PendingImports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Upload into a board → validate → preview diff → confirm. The uploaded file waits in
 * private storage between preview and confirm; only the uploader's session may confirm it.
 */
class ImportController extends Controller
{
    public function __construct(
        private ModuleImporter $importer,
        private PendingImports $pending,
    ) {}

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

        return redirect()->route('import.show', $this->pending->put($request, $board, $raw));
    }

    public function show(Request $request, string $token): View|RedirectResponse
    {
        [$raw, $board] = $this->pending->get($request, $token);

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
        [$raw, $board] = $this->pending->get($request, $token);

        if ($raw === null) {
            return $this->expired();
        }

        if (! $this->importer->analyze($raw, $board)->isValid()) {
            return redirect()->route('import.show', $token);
        }

        $module = $this->importer->import($raw, $board);

        $this->pending->forget($request, $token);

        return redirect()->route('modules.show', $module)->with('status', "Modul \"{$module->title}\" v{$module->version} berhasil diimpor ke board {$board->title}.");
    }

    private function expired(): RedirectResponse
    {
        return redirect()->route('dashboard')->with('error', 'Sesi impor tidak ditemukan atau sudah kedaluwarsa. Unggah ulang file-nya dari board.');
    }
}
