<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Enums\Tier;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'module_id', 'key', 'tier', 'type', 'objective', 'position', 'prompt', 'context',
    'spec', 'explanation', 'solution', 'source_url', 'content_hash',
])]
class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tier' => Tier::class,
            'type' => QuestionType::class,
            'spec' => 'array',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Module, $this>
     */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    /**
     * Answers shown to the learner as the reference solution.
     *
     * @return list<string>
     */
    public function acceptedAnswers(): array
    {
        return array_values($this->spec['answers'] ?? []);
    }

    /**
     * @return list<array{id: string, label: string, patterns: list<string>, required: bool, forbid: bool}>
     */
    public function checklist(): array
    {
        return array_values($this->spec['checklist'] ?? []);
    }
}
