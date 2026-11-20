<?php

namespace App\Http\Controllers;

use App\Models\Attempt;
use App\Models\Course;
use App\Services\Quiz\QuizService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, QuizService $quiz): View
    {
        $user = $request->user();
        $boards = Course::query()->with('modules')->orderBy('title')->get();

        // Tiers passed out of all tiers that have questions, per board.
        $progress = [];

        foreach ($boards as $board) {
            $passed = 0;
            $total = 0;

            foreach ($board->modules as $module) {
                foreach ($quiz->tierStatuses($module, $user) as $status) {
                    if ($status['question_count'] > 0) {
                        $total++;
                        $passed += $status['passed'] ? 1 : 0;
                    }
                }
            }

            $progress[$board->id] = ['passed' => $passed, 'total' => $total];
        }

        $attempts = Attempt::query()->whereBelongsTo($user)->with('module');

        return view('dashboard', [
            'boards' => $boards,
            'progress' => $progress,
            'activeAttempts' => (clone $attempts)->where('status', Attempt::STATUS_IN_PROGRESS)->latest('started_at')->get(),
            'recentAttempts' => (clone $attempts)->where('status', Attempt::STATUS_COMPLETED)->latest('completed_at')->limit(5)->get(),
        ]);
    }
}
