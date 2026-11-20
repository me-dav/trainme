<?php

namespace App\Services\Quiz;

use App\Enums\Tier;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Module;
use App\Models\Question;
use App\Models\User;
use App\Services\Grading\AnswerGrader;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

class QuizService
{
    public function __construct(private AnswerGrader $grader) {}

    /**
     * Per-tier progress of a user in a module.
     *
     * @return array<string, array{tier: Tier, question_count: int, quiz_size: int, best_score: float|null, attempt_count: int, passed: bool, unlocked: bool, in_progress: Attempt|null}>
     */
    public function tierStatuses(Module $module, User $user): array
    {
        $counts = $module->questions()->reorder()->toBase()
            ->selectRaw('tier, count(*) as aggregate')
            ->groupBy('tier')
            ->pluck('aggregate', 'tier');

        $attempts = Attempt::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($module)
            ->get()
            ->groupBy(fn (Attempt $attempt): string => $attempt->tier->value);

        $passScore = (float) config('dojo.pass_score');
        $statuses = [];
        $previousPassed = true;

        foreach (Tier::cases() as $tier) {
            $tierAttempts = $attempts->get($tier->value, collect());
            $completed = $tierAttempts->filter(fn (Attempt $attempt): bool => $attempt->isCompleted());
            $best = $completed->max('score');
            $questionCount = (int) ($counts[$tier->value] ?? 0);
            $passed = $best !== null && $best >= $passScore;

            $statuses[$tier->value] = [
                'tier' => $tier,
                'question_count' => $questionCount,
                'quiz_size' => min($questionCount, $module->quizSizeFor($tier)),
                'best_score' => $best === null ? null : (float) $best,
                'attempt_count' => $completed->count(),
                'passed' => $passed,
                'unlocked' => $previousPassed && $questionCount > 0,
                'in_progress' => $tierAttempts->first(fn (Attempt $attempt): bool => ! $attempt->isCompleted()),
            ];

            $previousPassed = $passed;
        }

        return $statuses;
    }

    /**
     * The unfinished attempt of this module, if any. While it exists the module's material,
     * question bank and old results stay closed, so the test can't be answered by looking things up.
     */
    public function activeAttempt(Module $module, User $user): ?Attempt
    {
        return Attempt::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($module)
            ->where('status', Attempt::STATUS_IN_PROGRESS)
            ->latest('id')
            ->first();
    }

    public function isUnlocked(Module $module, User $user, Tier $tier): bool
    {
        return $this->tierStatuses($module, $user)[$tier->value]['unlocked'];
    }

    /**
     * Resumes the user's unfinished attempt for this tier, or starts a new one.
     *
     * @throws LogicException when the tier is locked or empty.
     */
    public function startOrResume(Module $module, User $user, Tier $tier): Attempt
    {
        $status = $this->tierStatuses($module, $user)[$tier->value];

        if (! $status['unlocked']) {
            throw new LogicException('Tier ini masih terkunci.');
        }

        if ($status['in_progress'] !== null) {
            return $status['in_progress'];
        }

        return Attempt::query()->create([
            'user_id' => $user->id,
            'module_id' => $module->id,
            'tier' => $tier,
            'status' => Attempt::STATUS_IN_PROGRESS,
            'question_ids' => $this->pickQuestions($module, $user, $tier, $status['quiz_size']),
            'current_index' => 0,
            'started_at' => now(),
        ]);
    }

    /**
     * Picks questions, preferring ones last answered wrong, then never-seen ones,
     * then ones answered correctly — shuffled within each group.
     *
     * @return list<int>
     */
    public function pickQuestions(Module $module, User $user, Tier $tier, int $size): array
    {
        $questionIds = $module->questions()->where('tier', $tier->value)->pluck('id')->map(fn ($id): int => (int) $id);

        $lastResults = AttemptAnswer::query()
            ->whereIn('question_id', $questionIds)
            ->whereHas('attempt', fn ($query) => $query->whereBelongsTo($user))
            ->orderBy('id')
            ->get(['question_id', 'is_correct'])
            ->keyBy(fn (AttemptAnswer $answer): int => (int) $answer->question_id);

        $groups = $questionIds->groupBy(function (int $id) use ($lastResults): int {
            $last = $lastResults->get($id);

            return match (true) {
                $last === null => 1,
                ! $last->is_correct => 0,
                default => 2,
            };
        })->sortKeys();

        $picked = $groups->flatMap(fn (Collection $ids) => $ids->shuffle())->take($size);

        return $picked->shuffle()->map(fn ($id): int => (int) $id)->values()->all();
    }

    public function currentQuestion(Attempt $attempt): ?Question
    {
        $questionId = $attempt->currentQuestionId();

        return $questionId === null ? null : Question::withTrashed()->find($questionId);
    }

    public function currentAnswer(Attempt $attempt): ?AttemptAnswer
    {
        return $attempt->answers()->where('position', $attempt->current_index)->first();
    }

    /**
     * Grades and stores the answer for the current question. Answering twice keeps the first answer.
     */
    public function answer(Attempt $attempt, string $answer): AttemptAnswer
    {
        if ($attempt->isCompleted()) {
            throw new LogicException('Attempt sudah selesai.');
        }

        $existing = $this->currentAnswer($attempt);

        if ($existing !== null) {
            return $existing;
        }

        $question = $this->currentQuestion($attempt) ?? throw new LogicException('Soal tidak ditemukan.');
        $result = $this->grader->grade($question->type, $question->spec, $answer);

        return $attempt->answers()->create([
            'question_id' => $question->id,
            'position' => $attempt->current_index,
            'answer' => $answer,
            'score' => $result->score,
            'is_correct' => $result->isCorrect,
            'feedback' => $result->feedback,
        ]);
    }

    /**
     * Moves to the next question once the current one is answered; completes the attempt after the last.
     */
    public function advance(Attempt $attempt): Attempt
    {
        if ($attempt->isCompleted() || $this->currentAnswer($attempt) === null) {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt): Attempt {
            $attempt->current_index++;

            if ($attempt->current_index >= $attempt->questionCount()) {
                $this->complete($attempt);
            }

            $attempt->save();

            return $attempt;
        });
    }

    /**
     * Ends the attempt now: every question without an answer counts as wrong (score 0).
     */
    public function finish(Attempt $attempt): Attempt
    {
        if ($attempt->isCompleted()) {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt): Attempt {
            $answered = $attempt->answers()->pluck('position')->map(fn ($position): int => (int) $position)->all();

            foreach ($attempt->question_ids as $position => $questionId) {
                if (! in_array($position, $answered, true)) {
                    $attempt->answers()->create([
                        'question_id' => $questionId,
                        'position' => $position,
                        'answer' => '',
                        'score' => 0,
                        'is_correct' => false,
                        'feedback' => null,
                    ]);
                }
            }

            $attempt->current_index = $attempt->questionCount();
            $this->complete($attempt);
            $attempt->save();

            return $attempt;
        });
    }

    private function complete(Attempt $attempt): void
    {
        $attempt->status = Attempt::STATUS_COMPLETED;
        $attempt->completed_at = now();
        $attempt->score = round((float) $attempt->answers()->avg('score') * 100, 2);
    }
}
