<?php

namespace Database\Factories;

use App\Enums\QuestionType;
use App\Enums\Tier;
use App\Models\Module;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    /**
     * A command question whose only accepted answer is `chmod u+x deploy.sh`.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'key' => fake()->unique()->bothify('q##??'),
            'tier' => Tier::A,
            'type' => QuestionType::Command,
            'objective' => 'basics',
            'position' => 0,
            'prompt' => 'Tambahkan izin eksekusi untuk owner pada `deploy.sh`.',
            'context' => null,
            'spec' => [
                'answers' => ['chmod u+x deploy.sh'],
                'patterns' => [],
                'flags_any_order' => false,
                'tests' => ['pass' => [], 'fail' => []],
            ],
            'explanation' => '`u` adalah owner.',
            'solution' => null,
            'source_url' => 'https://example.com/chmod',
            'content_hash' => fake()->sha256(),
        ];
    }

    public function tier(Tier $tier): static
    {
        return $this->state(fn (): array => ['tier' => $tier]);
    }

    /**
     * A scenario question: `namei` is required, `chmod 777` is forbidden.
     */
    public function scenario(): static
    {
        return $this->state(fn (): array => [
            'tier' => Tier::C,
            'type' => QuestionType::Scenario,
            'prompt' => 'Perbaiki permission storage.',
            'spec' => [
                'checklist' => [
                    ['id' => 'diagnose', 'label' => 'Diagnosis', 'patterns' => ['namei\s+-l'], 'required' => true, 'forbid' => false],
                    ['id' => 'fix', 'label' => 'Perbaikan', 'patterns' => ['chgrp\s+www-data'], 'required' => false, 'forbid' => false],
                    ['id' => 'no-777', 'label' => 'Tanpa 777', 'patterns' => ['chmod\s+777'], 'required' => false, 'forbid' => true],
                ],
                'tests' => ['pass' => [], 'fail' => []],
            ],
            'solution' => "```bash\nnamei -l /srv/app\n```",
        ]);
    }
}
