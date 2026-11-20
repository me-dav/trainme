<?php

namespace App\Models;

use App\Enums\Tier;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'course_id', 'slug', 'title', 'summary', 'position', 'version', 'status', 'body',
    'objectives', 'sources', 'tested_on', 'quiz_sizes', 'content_updated_on', 'content_hash', 'imported_at',
])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'sources' => 'array',
            'tested_on' => 'array',
            'quiz_sizes' => 'array',
            'imported_at' => 'datetime',
            'position' => 'integer',
            'version' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return BelongsTo<Course, $this>
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position');
    }

    /**
     * @return HasMany<Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function isVerified(): bool
    {
        return $this->status === 'verified';
    }

    /**
     * Number of questions drawn for one quiz attempt of the given tier.
     */
    public function quizSizeFor(Tier $tier): int
    {
        $sizes = $this->quiz_sizes ?? [];

        return (int) ($sizes[$tier->value] ?? config('dojo.quiz_sizes.'.$tier->value));
    }

    public function objectiveText(?string $objectiveId): ?string
    {
        foreach ($this->objectives ?? [] as $objective) {
            if ($objective['id'] === $objectiveId) {
                return $objective['text'];
            }
        }

        return null;
    }
}
