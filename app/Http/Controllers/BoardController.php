<?php

namespace App\Http\Controllers;

use App\Models\AiImport;
use App\Models\Course;
use App\Services\Ai\ChatClient;
use App\Services\Quiz\QuizService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * A board groups the modules of one subject (Linux, Docker, ...). In the code it is a Course,
 * so the frontmatter `course.slug` of CLI imports keeps matching it.
 */
class BoardController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('board', [
            'title' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:300'],
        ], [
            'title.required' => 'Nama board wajib diisi.',
            'title.max' => 'Nama board maksimal 60 karakter.',
            'description.max' => 'Deskripsi maksimal 300 karakter.',
        ]);

        $slug = Str::slug($validated['title']);

        if ($slug === '') {
            return back()->withInput()->withErrors(['title' => 'Nama board harus mengandung huruf atau angka.'], 'board');
        }

        if (Course::query()->where('slug', $slug)->exists()) {
            return back()->withInput()->withErrors(['title' => "Board dengan nama itu sudah ada ({$slug})."], 'board');
        }

        $board = Course::query()->create([
            'slug' => $slug,
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('boards.show', $board)->with('status', "Board \"{$board->title}\" dibuat. Sekarang impor modul pertamanya.");
    }

    public function show(Request $request, Course $board, QuizService $quiz, ChatClient $ai): View
    {
        $board->load('modules');
        $user = $request->user();

        return view('boards.show', [
            'board' => $board,
            'statuses' => $board->modules->mapWithKeys(fn ($module): array => [$module->id => $quiz->tierStatuses($module, $user)])->all(),
            'aiEnabled' => $ai->isConfigured(),
            'activeModuleIds' => $board->modules->filter(fn ($module): bool => $quiz->activeAttempt($module, $user) !== null)->pluck('id')->all(),
        ]);
    }

    /**
     * The slug stays fixed: it is in URLs and in the `course.slug` of module files.
     */
    public function update(Request $request, Course $board): RedirectResponse
    {
        $validated = $request->validateWithBag('boardEdit', [
            'title' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:300'],
        ], [
            'title.required' => 'Nama board wajib diisi.',
            'title.max' => 'Nama board maksimal 60 karakter.',
            'description.max' => 'Deskripsi maksimal 300 karakter.',
        ]);

        $board->update([
            'title' => trim($validated['title']),
            'description' => $validated['description'] ?? null,
        ]);

        return redirect()->route('boards.show', $board)->with('status', 'Board diperbarui.');
    }

    /**
     * Only empty boards can be deleted, so no module or test history disappears by accident.
     */
    /**
     * Deletes the board with everything in it: modules, their questions and test history, and AI imports
     * (the database cascades). A board with modules must be confirmed by typing its slug.
     */
    public function destroy(Request $request, Course $board): RedirectResponse
    {
        $moduleCount = $board->modules()->count();

        if ($moduleCount > 0 && $request->input('confirm') !== $board->slug) {
            return redirect()->route('boards.show', $board)
                ->withErrors(['confirm' => "Ketik `{$board->slug}` untuk mengonfirmasi penghapusan."], 'boardDelete');
        }

        $documents = AiImport::query()->where('course_id', $board->id)->pluck('source_path')->all();

        $board->delete();
        Storage::disk('local')->delete($documents);

        return redirect()->route('dashboard')->with('status', $moduleCount > 0
            ? "Board \"{$board->title}\" beserta {$moduleCount} modul dihapus."
            : "Board \"{$board->title}\" dihapus.");
    }

    /**
     * The module template, with the module id prefixed by this board's slug.
     */
    public function template(Course $board): Response
    {
        $template = str_replace('__BOARD__', $board->slug, File::get(resource_path('templates/module-template.md')));

        return response($template, 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="template-modul-'.$board->slug.'.md"',
        ]);
    }
}
