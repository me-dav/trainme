<?php

namespace Tests\Feature;

use App\Enums\QuestionType;
use App\Services\Grading\AnswerGrader;
use Tests\TestCase;

class AnswerGraderTest extends TestCase
{
    private AnswerGrader $grader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grader = $this->app->make(AnswerGrader::class);
    }

    public function test_command_matches_canonical_answer(): void
    {
        $result = $this->grader->grade(QuestionType::Command, ['answers' => ['chmod u+x deploy.sh']], '$ sudo chmod  u+x deploy.sh');

        $this->assertTrue($result->isCorrect);
        $this->assertSame(1.0, $result->score);
        $this->assertSame('answer', $result->feedback['matched_by']);
    }

    public function test_command_rejects_a_different_command(): void
    {
        $result = $this->grader->grade(QuestionType::Command, ['answers' => ['chmod u+x deploy.sh']], 'chmod +x deploy.sh');

        $this->assertFalse($result->isCorrect);
        $this->assertSame(0.0, $result->score);
    }

    public function test_flag_order_is_only_ignored_when_enabled(): void
    {
        $spec = ['answers' => ['ls -ld /var/www']];

        $this->assertFalse($this->grader->grade(QuestionType::Command, $spec, 'ls -dl /var/www')->isCorrect);

        $spec['flags_any_order'] = true;
        $result = $this->grader->grade(QuestionType::Command, $spec, 'ls -dl /var/www');

        $this->assertTrue($result->isCorrect);
        $this->assertSame('flags', $result->feedback['matched_by']);
    }

    public function test_patterns_accept_variants_and_are_case_sensitive_for_commands(): void
    {
        $spec = ['answers' => ['chmod 640 config.ini'], 'patterns' => ['^chmod 0?640 config\.ini$']];

        $this->assertTrue($this->grader->grade(QuestionType::Command, $spec, 'chmod 0640 config.ini')->isCorrect);
        $this->assertFalse($this->grader->grade(QuestionType::Command, $spec, 'CHMOD 0640 config.ini')->isCorrect);
    }

    public function test_text_answers_are_case_insensitive(): void
    {
        $spec = ['answers' => ['sticky bit'], 'patterns' => ['^sticky$']];

        $this->assertTrue($this->grader->grade(QuestionType::Text, $spec, 'Sticky Bit')->isCorrect);
        $this->assertTrue($this->grader->grade(QuestionType::Text, $spec, 'STICKY')->isCorrect);
        $this->assertFalse($this->grader->grade(QuestionType::Text, $spec, 'setgid')->isCorrect);
    }

    public function test_blank_answer_is_wrong(): void
    {
        $this->assertFalse($this->grader->grade(QuestionType::Text, ['answers' => ['750']], '   ')->isCorrect);
    }

    public function test_scenario_passes_with_required_items_and_no_forbidden_ones(): void
    {
        $result = $this->grader->grade(QuestionType::Scenario, $this->scenarioSpec(), "namei -l /srv/app\nchgrp -R www-data /srv/app");

        $this->assertTrue($result->isCorrect);
        $this->assertSame(1.0, $result->score);
        $this->assertSame(1.0, $result->feedback['ratio']);
    }

    public function test_scenario_fails_when_a_required_item_is_missing(): void
    {
        $result = $this->grader->grade(QuestionType::Scenario, $this->scenarioSpec(), 'chgrp -R www-data /srv/app');

        $this->assertFalse($result->isCorrect);
        $this->assertSame(0.25, $result->score);
    }

    public function test_scenario_fails_when_a_forbidden_item_is_hit_even_if_everything_else_is_there(): void
    {
        $answer = "namei -l /srv/app\nchgrp -R www-data /srv/app\nchmod -R 777 /srv/app";
        $result = $this->grader->grade(QuestionType::Scenario, $this->scenarioSpec(), $answer);

        $this->assertFalse($result->isCorrect);
        $this->assertTrue(collect($result->feedback['checklist'])->firstWhere('id', 'no-777')['hit']);
    }

    public function test_scenario_pass_ratio_can_be_set_per_question(): void
    {
        $spec = $this->scenarioSpec();
        $spec['pass_score'] = 1.0;

        $this->assertFalse($this->grader->grade(QuestionType::Scenario, $spec, 'namei -l /srv/app')->isCorrect);

        $spec['pass_score'] = 0.5;
        $this->assertTrue($this->grader->grade(QuestionType::Scenario, $spec, 'namei -l /srv/app')->isCorrect);
    }

    /**
     * @return array<string, mixed>
     */
    private function scenarioSpec(): array
    {
        return [
            'checklist' => [
                ['id' => 'diagnose', 'label' => 'Diagnosis', 'patterns' => ['namei\s+-l'], 'required' => true, 'forbid' => false],
                ['id' => 'fix', 'label' => 'Group', 'patterns' => ['chgrp\s+(-R\s+)?www-data'], 'required' => false, 'forbid' => false],
                ['id' => 'no-777', 'label' => 'Tanpa 777', 'patterns' => ['chmod\s+(-R\s+)?777'], 'required' => false, 'forbid' => true],
            ],
        ];
    }
}
