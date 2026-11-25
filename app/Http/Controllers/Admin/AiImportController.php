<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AiImportStatus;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateModuleFromDocument;
use App\Models\AiImport;
use App\Models\Course;
use App\Services\Ai\ChatClient;
use App\Services\Content\PendingImports;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Upload a PDF/Word document → AI writes the module file in the background → the result
 * joins the manual flow at the preview step.
 */
class AiImportController extends Controller
{
    public function store(Request $request, Course $board, ChatClient $client): RedirectResponse
    {
        $maxKilobytes = (int) config('services.llm.max_upload_kb');

        $request->validateWithBag('aiImport', [
            'document' => ['required', 'file', 'max:'.$maxKilobytes, 'extensions:pdf,docx'],
        ], [
            'document.required' => 'Pilih file PDF atau Word.',
            'document.extensions' => 'File harus berupa PDF atau Word (.docx).',
            'document.max' => 'Ukuran file maksimal '.intdiv($maxKilobytes, 1024).' MB.',
        ]);

        if (! $client->isConfigured()) {
            return back()->withErrors(['document' => 'Impor dengan AI belum dikonfigurasi di server.'], 'aiImport');
        }

        $document = $request->file('document');
        $extension = strtolower($document->getClientOriginalExtension());

        $aiImport = AiImport::query()->create([
            'user_id' => $request->user()->id,
            'course_id' => $board->id,
            'original_name' => Str::limit($document->getClientOriginalName(), 200, ''),
            'source_path' => $document->storeAs('ai-imports', Str::uuid().'.'.$extension, 'local'),
            'status' => AiImportStatus::Pending,
        ]);

        GenerateModuleFromDocument::dispatch($aiImport);

        return redirect()->route('ai-imports.show', $aiImport);
    }

    /**
     * Shows progress while the job runs; once the file is ready, hands it to the preview page.
     */
    public function show(Request $request, AiImport $aiImport, PendingImports $pending): View|RedirectResponse
    {
        abort_unless($aiImport->user_id === $request->user()->id, 404);

        $aiImport->load('course');

        if ($aiImport->status === AiImportStatus::Ready) {
            return redirect()->route('import.show', $pending->put($request, $aiImport->course, (string) $aiImport->result));
        }

        return view('ai-imports.show', ['aiImport' => $aiImport]);
    }
}
