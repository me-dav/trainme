<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'course_id' => Course::factory(),
            'slug' => Str::slug($title),
            'title' => Str::title($title),
            'summary' => fake()->sentence(),
            'position' => 1,
            'version' => 1,
            'status' => 'verified',
            'body' => "# {$title}\n\n## Bagian satu\n\nIsi materi.",
            'objectives' => [['id' => 'basics', 'text' => 'Dasar']],
            'sources' => [['title' => 'Docs', 'url' => 'https://example.com/docs']],
            'tested_on' => [],
            'quiz_sizes' => ['A' => 2, 'B' => 2, 'C' => 1],
            'content_updated_on' => '2026-11-19',
            'content_hash' => hash('sha256', $title),
            'imported_at' => now(),
        ];
    }
}
