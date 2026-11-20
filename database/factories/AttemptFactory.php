<?php

namespace Database\Factories;

use App\Enums\Tier;
use App\Models\Attempt;
use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attempt>
 */
class AttemptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'module_id' => Module::factory(),
            'tier' => Tier::A,
            'status' => Attempt::STATUS_IN_PROGRESS,
            'question_ids' => [],
            'current_index' => 0,
            'score' => null,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(float $score): static
    {
        return $this->state(fn (): array => [
            'status' => Attempt::STATUS_COMPLETED,
            'score' => $score,
            'completed_at' => now(),
        ]);
    }
}
