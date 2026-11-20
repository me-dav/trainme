<?php

namespace App\Http\Controllers;

use App\Enums\Tier;
use App\Models\Attempt;
use App\Models\Module;
use App\Models\Question;
use App\Services\Quiz\QuizService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AttemptController extends Controller
{
    public function __construct(private QuizService $quiz) {}

    public function store(Request $request, Module $module, Tier $tier): RedirectResponse
    {
        if (! $this->quiz->isUnlocked($module, $request->user(), $tier)) {
            return redirect()
                ->route('modules.show', $module)
                ->with('error', "{$tier->label()} masih terkunci. Lulus tier sebelumnya minimal ".config('dojo.pass_score').'% dulu.');
        }

        $active = $this->quiz->activeAttempt($module, $request->user());

        if ($active !== null && $active->tier !== $tier) {
            return redirect()->route('attempts.show', $active)
                ->with('error', "Tes {$active->tier->label()} masih berjalan. Selesaikan atau akhiri dulu.");
        }

        $attempt = $this->quiz->startOrResume($module, $request->user(), $tier);

        return redirect()->route('attempts.show', $attempt);
    }

    public function show(Attempt $attempt): View|RedirectResponse
    {
        Gate::authorize('view', $attempt);

        $attempt->load('module.course');

        if ($attempt->isCompleted()) {
            // An old result reveals answers, so it stays closed while a test of the same module runs.
            $active = $this->quiz->activeAttempt($attempt->module, $attempt->user);

            if ($active !== null) {
                return redirect()->route('attempts.show', $active)
                    ->with('error', "Tes {$active->tier->label()} sedang berjalan. Hasil sebelumnya bisa dilihat setelah tes selesai.");
            }

            $answers = $attempt->answers()->with('question')->get();
            $nextTier = $attempt->tier->next();

            return view('attempts.summary', [
                'attempt' => $attempt,
                'answers' => $answers,
                'nextTier' => $nextTier,
                'nextTierUnlocked' => $nextTier !== null && $attempt->passed()
                    && $this->quiz->isUnlocked($attempt->module, $attempt->user, $nextTier),
            ]);
        }

        return view('attempts.show', [
            'attempt' => $attempt,
            'question' => $this->quiz->currentQuestion($attempt),
            'answer' => $this->quiz->currentAnswer($attempt),
        ]);
    }

    public function answer(Request $request, Attempt $attempt): RedirectResponse
    {
        Gate::authorize('view', $attempt);

        $validated = $request->validate([
            'answer' => ['required', 'string', 'max:10000'],
        ], [
            'answer.required' => 'Jawaban belum diisi.',
        ]);

        if (! $attempt->isCompleted()) {
            $this->quiz->answer($attempt, $validated['answer']);
        }

        return redirect()->route('attempts.show', $attempt);
    }

    public function next(Attempt $attempt): RedirectResponse
    {
        Gate::authorize('view', $attempt);

        $this->quiz->advance($attempt);

        return redirect()->route('attempts.show', $attempt);
    }

    /**
     * Ends the test early; unanswered questions count as wrong.
     */
    public function finish(Attempt $attempt): RedirectResponse
    {
        Gate::authorize('view', $attempt);

        $this->quiz->finish($attempt);

        return redirect()->route('attempts.show', $attempt);
    }

    /**
     * Skipping records an empty (wrong) answer so the reference solution is shown.
     */
    public function skip(Attempt $attempt): RedirectResponse
    {
        Gate::authorize('view', $attempt);

        if (! $attempt->isCompleted() && $this->quiz->currentQuestion($attempt) instanceof Question) {
            $this->quiz->answer($attempt, '');
        }

        return redirect()->route('attempts.show', $attempt);
    }
}
