<?php

namespace Tests\Feature;

use App\Enums\Tier;
use App\Models\Attempt;
use App\Models\Module;
use App\Models\User;
use App\Services\Content\ModuleImporter;
use App\Services\Quiz\QuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class QuizFlowTest extends TestCase
{
    use RefreshDatabase;

    private Module $module;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = $this->app->make(ModuleImporter::class)->import(File::get(base_path('tests/Fixtures/mini-module.md')));
        $this->user = User::factory()->create();
    }

    /**
     * Correct answers for the fixture module, keyed by question key.
     *
     * @var array<string, string>
     */
    private const CORRECT = [
        'a01' => 'chmod u+x deploy.sh',
        'a02' => '750',
        'b01' => 'ls -dl /srv',
        'c01' => "namei -l /srv/app/storage\nchgrp -R www-data /srv/app/storage",
    ];

    private function startAttempt(Tier $tier): Attempt
    {
        $this->actingAs($this->user)
            ->post(route('attempts.store', [$this->module, $tier->value]))
            ->assertRedirect();

        return Attempt::query()->where('tier', $tier->value)->latest('id')->firstOrFail();
    }

    /**
     * Answers every question of the attempt; `$wrongKeys` get a wrong answer.
     *
     * @param  list<string>  $wrongKeys
     */
    private function finish(Attempt $attempt, array $wrongKeys = []): Attempt
    {
        foreach ($attempt->question_ids as $questionId) {
            $key = $this->module->questions()->findOrFail($questionId)->key;
            $answer = in_array($key, $wrongKeys, true) ? 'salah' : self::CORRECT[$key];

            $this->post(route('attempts.answer', $attempt), ['answer' => $answer])->assertRedirect(route('attempts.show', $attempt));
            $this->post(route('attempts.next', $attempt))->assertRedirect(route('attempts.show', $attempt));
        }

        return $attempt->fresh();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('modules.show', $this->module))->assertRedirect(route('login'));
    }

    public function test_dashboard_and_module_page_render(): void
    {
        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Test Course')
            ->assertSee('1 modul');

        $this->get(route('boards.show', $this->module->course))
            ->assertOk()
            ->assertSee('Mini Module');

        $this->actingAs($this->user)->get(route('modules.show', $this->module))
            ->assertOk()
            ->assertSee('Bagian satu')
            ->assertSee('callout-insight', false)
            ->assertSee('language-mermaid', false)
            ->assertSee('Mulai');
    }

    public function test_question_bank_shows_answers(): void
    {
        $this->actingAs($this->user)->get(route('modules.bank', $this->module))
            ->assertOk()
            ->assertSee('chmod u+x deploy.sh')
            ->assertSee('Tidak memakai 777');
    }

    public function test_a_perfect_attempt_scores_100_and_unlocks_the_next_tier(): void
    {
        $attempt = $this->startAttempt(Tier::A);
        $this->assertCount(2, $attempt->question_ids);

        $this->get(route('attempts.show', $attempt))->assertOk()->assertSee('Periksa');

        $attempt = $this->finish($attempt);

        $this->assertTrue($attempt->isCompleted());
        $this->assertSame(100.0, $attempt->score);
        $this->assertTrue($this->app->make(QuizService::class)->isUnlocked($this->module, $this->user, Tier::B));

        $this->get(route('attempts.show', $attempt))->assertOk()->assertSee('100%')->assertSee('Tier B · Advanced terbuka');
    }

    public function test_a_failed_attempt_keeps_the_next_tier_locked(): void
    {
        $attempt = $this->finish($this->startAttempt(Tier::A), wrongKeys: ['a02']);

        $this->assertSame(50.0, $attempt->score);
        $this->assertFalse($attempt->passed());

        $this->post(route('attempts.store', [$this->module, 'B']))
            ->assertRedirect(route('modules.show', $this->module))
            ->assertSessionHas('error');
        $this->assertSame(0, Attempt::query()->where('tier', 'B')->count());
    }

    public function test_locked_tiers_cannot_be_started(): void
    {
        $this->actingAs($this->user)
            ->post(route('attempts.store', [$this->module, 'C']))
            ->assertRedirect(route('modules.show', $this->module));

        $this->assertSame(0, Attempt::query()->count());
    }

    public function test_feedback_is_shown_after_answering_and_the_first_answer_is_kept(): void
    {
        $attempt = $this->startAttempt(Tier::A);

        $this->post(route('attempts.answer', $attempt), ['answer' => 'salah']);
        $this->post(route('attempts.answer', $attempt), ['answer' => 'jawaban kedua']);

        $this->assertSame(1, $attempt->answers()->count());
        $this->assertSame('salah', $attempt->answers()->first()->answer);
        $this->get(route('attempts.show', $attempt))->assertOk()->assertSee('Belum tepat')->assertSee('Sumber:');
    }

    public function test_next_does_nothing_until_the_question_is_answered(): void
    {
        $attempt = $this->startAttempt(Tier::A);

        $this->post(route('attempts.next', $attempt));

        $this->assertSame(0, $attempt->fresh()->current_index);
    }

    public function test_skipping_records_a_wrong_answer_and_reveals_the_solution(): void
    {
        $attempt = $this->startAttempt(Tier::A);

        $this->post(route('attempts.skip', $attempt))->assertRedirect(route('attempts.show', $attempt));

        $this->assertFalse($attempt->answers()->first()->is_correct);
        $this->get(route('attempts.show', $attempt))->assertSee('Dilewati');
    }

    public function test_starting_again_resumes_the_unfinished_attempt(): void
    {
        $first = $this->startAttempt(Tier::A);
        $second = $this->startAttempt(Tier::A);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Attempt::query()->count());
    }

    public function test_scenario_feedback_lists_the_checklist(): void
    {
        $this->finish($this->startAttempt(Tier::A));
        $this->finish($this->startAttempt(Tier::B));
        $attempt = $this->startAttempt(Tier::C);

        $this->post(route('attempts.answer', $attempt), ['answer' => 'chmod -R 777 /srv/app/storage']);

        $this->get(route('attempts.show', $attempt))
            ->assertOk()
            ->assertSee('Cek path dengan namei')
            ->assertSee('terdeteksi — anti-pattern');
        $this->assertFalse($attempt->answers()->first()->is_correct);
    }

    public function test_previously_wrong_questions_are_picked_first(): void
    {
        $this->finish($this->startAttempt(Tier::A), wrongKeys: ['a02']);

        $a02 = $this->module->questions()->where('key', 'a02')->value('id');
        $picked = $this->app->make(QuizService::class)->pickQuestions($this->module, $this->user, Tier::A, 1);

        $this->assertSame([$a02], $picked);
    }

    public function test_users_cannot_see_other_users_attempts(): void
    {
        $attempt = $this->startAttempt(Tier::A);

        $this->actingAs(User::factory()->create())
            ->get(route('attempts.show', $attempt))
            ->assertForbidden();

        $this->post(route('attempts.answer', $attempt), ['answer' => 'x'])->assertForbidden();
    }

    public function test_material_bank_and_old_results_are_locked_while_a_test_runs(): void
    {
        $old = $this->finish($this->startAttempt(Tier::A), wrongKeys: ['a02']);
        $running = $this->startAttempt(Tier::A);

        $this->get(route('modules.show', $this->module))->assertRedirect(route('attempts.show', $running))->assertSessionHas('error');
        $this->get(route('modules.bank', $this->module))->assertRedirect(route('attempts.show', $running));
        $this->get(route('attempts.show', $old))->assertRedirect(route('attempts.show', $running));

        $this->get(route('attempts.show', $running))->assertOk()
            ->assertSee('materi terkunci selama tes')
            ->assertSee('Akhiri tes')
            ->assertDontSee(route('modules.show', $this->module).'"', false);
    }

    public function test_another_tier_cannot_start_while_a_test_runs(): void
    {
        $this->finish($this->startAttempt(Tier::A));
        $running = $this->startAttempt(Tier::A);

        $this->post(route('attempts.store', [$this->module, 'B']))->assertRedirect(route('attempts.show', $running));
        $this->assertSame(0, Attempt::query()->where('tier', 'B')->count());
    }

    public function test_finishing_early_counts_unanswered_questions_as_wrong_and_unlocks_the_material(): void
    {
        $attempt = $this->startAttempt(Tier::A);
        $firstKey = $this->module->questions()->findOrFail($attempt->question_ids[0])->key;
        $this->post(route('attempts.answer', $attempt), ['answer' => self::CORRECT[$firstKey]]);

        $this->post(route('attempts.finish', $attempt))->assertRedirect(route('attempts.show', $attempt));

        $attempt->refresh();
        $this->assertTrue($attempt->isCompleted());
        $this->assertSame(50.0, $attempt->score);
        $this->assertSame(2, $attempt->answers()->count());

        $this->get(route('attempts.show', $attempt))->assertOk()->assertSee('Kembali ke materi');
        $this->get(route('modules.show', $this->module))->assertOk();
    }
}
