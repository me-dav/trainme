<?php

namespace App\Services\Grading;

/**
 * Outcome of grading one answer.
 */
final readonly class GradeResult
{
    /**
     * @param  float  $score  Between 0 and 1.
     * @param  array{matched_by?: string|null, checklist?: list<array{id: string, label: string, hit: bool, required: bool, forbid: bool}>, ratio?: float, pass_ratio?: float}  $feedback
     */
    public function __construct(
        public float $score,
        public bool $isCorrect,
        public array $feedback = [],
    ) {}

    public static function wrong(): self
    {
        return new self(0.0, false, ['matched_by' => null]);
    }
}
