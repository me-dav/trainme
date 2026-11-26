<?php

namespace App\Http\Controllers;

use App\Enums\Tier;
use App\Models\Attempt;
use App\Models\Module;
use App\Services\Quiz\QuizService;
use App\Support\MarkdownRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __construct(private QuizService $quiz) {}

    public function show(Request $request, Module $module, MarkdownRenderer $markdown): View|RedirectResponse
    {
        if ($active = $this->quiz->activeAttempt($module, $request->user())) {
            return $this->backToTest($active);
        }

        $module->load('course');

        return view('modules.show', [
            'module' => $module,
            'document' => $markdown->document($module->body),
            'statuses' => $this->quiz->tierStatuses($module, $request->user()),
            'attemptCount' => $module->attempts()->count(),
        ]);
    }

    /**
     * Full question bank with answers, for reviewing imported content.
     */
    public function bank(Request $request, Module $module): View|RedirectResponse
    {
        if ($active = $this->quiz->activeAttempt($module, $request->user())) {
            return $this->backToTest($active);
        }

        $module->load('course');

        return view('modules.bank', [
            'module' => $module,
            'questionsByTier' => $module->questions()->get()->groupBy(fn ($question): string => $question->tier->value),
            'tiers' => Tier::cases(),
        ]);
    }

    /**
     * Uploaded modules start as draft; the learner marks them verified after checking the content.
     */
    public function updateStatus(Request $request, Module $module): RedirectResponse
    {
        $validated = $request->validate(['status' => ['required', Rule::in(['draft', 'verified'])]]);

        $module->update(['status' => $validated['status']]);

        return redirect()->route('modules.show', $module)->with('status', $module->isVerified()
            ? 'Modul ditandai terverifikasi.'
            : 'Modul dikembalikan ke draft.');
    }

    /**
     * Deletes the module with its questions and test history (the database cascades).
     */
    public function destroy(Module $module): RedirectResponse
    {
        $board = $module->course;

        $module->delete();

        return redirect()->route('boards.show', $board)->with('status', "Modul \"{$module->title}\" dihapus.");
    }

    private function backToTest(Attempt $attempt): RedirectResponse
    {
        return redirect()->route('attempts.show', $attempt)
            ->with('error', "Tes {$attempt->tier->label()} sedang berjalan. Materi baru bisa dibuka lagi setelah tes selesai atau diakhiri.");
    }
}
