<?php

namespace App\Services\Grading;

use App\Enums\QuestionType;

/**
 * Grades answers deterministically (no AI): exact canonical match, optional
 * option-order-insensitive match, then author-supplied regex patterns.
 */
class AnswerGrader
{
    public function __construct(private AnswerNormalizer $normalizer) {}

    /**
     * @param  array{answers?: list<string>, patterns?: list<string>, flags_any_order?: bool, checklist?: list<array<string, mixed>>, pass_score?: float}  $spec
     */
    public function grade(QuestionType $type, array $spec, string $answer): GradeResult
    {
        if (trim($answer) === '') {
            return GradeResult::wrong();
        }

        return match ($type) {
            QuestionType::Command => $this->gradeCommand($spec, $answer),
            QuestionType::Text => $this->gradeText($spec, $answer),
            QuestionType::Scenario => $this->gradeScenario($spec, $answer),
        };
    }

    /**
     * Wraps an author pattern in delimiters with the given modifiers.
     */
    public static function delimit(string $pattern, string $modifiers = ''): string
    {
        return '~'.str_replace('~', '\~', $pattern).'~u'.$modifiers;
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function gradeCommand(array $spec, string $answer): GradeResult
    {
        $tokens = $this->normalizer->commandTokens($answer);
        $canonical = implode(' ', $tokens);

        foreach ($spec['answers'] ?? [] as $accepted) {
            if ($canonical === $this->normalizer->command($accepted)) {
                return $this->correct('answer');
            }
        }

        if ($spec['flags_any_order'] ?? false) {
            $flagsForm = $this->normalizer->flagsCanonical($tokens);

            foreach ($spec['answers'] ?? [] as $accepted) {
                if ($flagsForm === $this->normalizer->flagsCanonical($this->normalizer->commandTokens($accepted))) {
                    return $this->correct('flags');
                }
            }
        }

        foreach ($spec['patterns'] ?? [] as $pattern) {
            if (preg_match(self::delimit($pattern), $canonical) === 1) {
                return $this->correct('pattern');
            }
        }

        return GradeResult::wrong();
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function gradeText(array $spec, string $answer): GradeResult
    {
        $canonical = $this->normalizer->text($answer);

        foreach ($spec['answers'] ?? [] as $accepted) {
            if ($canonical === $this->normalizer->text($accepted)) {
                return $this->correct('answer');
            }
        }

        foreach ($spec['patterns'] ?? [] as $pattern) {
            if (preg_match(self::delimit($pattern, 'i'), $canonical) === 1) {
                return $this->correct('pattern');
            }
        }

        return GradeResult::wrong();
    }

    /**
     * A scenario passes when the hit ratio of non-forbidden checklist items reaches the pass ratio,
     * every required item is hit and no forbidden item is hit. Passing earns full credit;
     * failing earns half of the hit ratio as partial credit.
     *
     * @param  array<string, mixed>  $spec
     */
    private function gradeScenario(array $spec, string $answer): GradeResult
    {
        $text = $this->normalizer->scenario($answer);
        $items = [];
        $scored = 0;
        $hits = 0;
        $requiredMissing = false;
        $forbiddenHit = false;

        foreach ($spec['checklist'] ?? [] as $item) {
            $hit = false;

            foreach ($item['patterns'] as $pattern) {
                if (preg_match(self::delimit($pattern, 'im'), $text) === 1) {
                    $hit = true;
                    break;
                }
            }

            $forbid = (bool) ($item['forbid'] ?? false);
            $required = (bool) ($item['required'] ?? false);

            if ($forbid) {
                $forbiddenHit = $forbiddenHit || $hit;
            } else {
                $scored++;
                $hits += $hit ? 1 : 0;
                $requiredMissing = $requiredMissing || ($required && ! $hit);
            }

            $items[] = [
                'id' => (string) $item['id'],
                'label' => (string) $item['label'],
                'hit' => $hit,
                'required' => $required,
                'forbid' => $forbid,
            ];
        }

        $ratio = $scored > 0 ? $hits / $scored : 0.0;
        $passRatio = (float) ($spec['pass_score'] ?? config('dojo.scenario_pass_ratio'));
        $isCorrect = ! $forbiddenHit && ! $requiredMissing && $ratio >= $passRatio;
        $score = $isCorrect ? 1.0 : $ratio / 2;

        return new GradeResult(round($score, 3), $isCorrect, [
            'checklist' => $items,
            'ratio' => round($ratio, 3),
            'pass_ratio' => $passRatio,
        ]);
    }

    private function correct(string $matchedBy): GradeResult
    {
        return new GradeResult(1.0, true, ['matched_by' => $matchedBy]);
    }
}
