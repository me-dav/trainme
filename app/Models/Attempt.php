<?php

namespace App\Models;

use App\Enums\Tier;
use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'module_id', 'tier', 'status', 'question_ids', 'current_index', 'score', 'started_at', 'completed_at'])]
class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tier' => Tier::class,
            'question_ids' => 'array',
            'current_index' => 'integer',
            'score' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * @return HasMany<AttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class)->orderBy('position');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function questionCount(): int
    {
        return count($this->question_ids);
    }

    public function currentQuestionId(): ?int
    {
        return $this->question_ids[$this->current_index] ?? null;
    }

    public function passed(): bool
    {
        return $this->isCompleted() && $this->score >= config('dojo.pass_score');
    }
}
