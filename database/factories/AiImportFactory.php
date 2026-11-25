<?php

namespace Database\Factories;

use App\Enums\AiImportStatus;
use App\Models\AiImport;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiImport>
 */
class AiImportFactory extends Factory
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
            'course_id' => Course::factory(),
            'original_name' => fake()->slug(2).'.pdf',
            'source_path' => 'ai-imports/'.fake()->uuid().'.pdf',
            'status' => AiImportStatus::Pending,
        ];
    }

    public function ready(string $result): static
    {
        return $this->state(fn (): array => [
            'status' => AiImportStatus::Ready,
            'attempts' => 1,
            'result' => $result,
            'finished_at' => now(),
        ]);
    }

    /**
     * @param  list<string>  $validationErrors
     */
    public function failed(string $error, array $validationErrors = []): static
    {
        return $this->state(fn (): array => [
            'status' => AiImportStatus::Failed,
            'attempts' => 3,
            'error' => $error,
            'validation_errors' => $validationErrors === [] ? null : $validationErrors,
            'finished_at' => now(),
        ]);
    }
}
