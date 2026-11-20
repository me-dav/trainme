<?php

namespace App\Services\Content;

/**
 * Validation result plus what an import would change compared to the database.
 */
final readonly class ImportAnalysis
{
    /**
     * @param  array{is_new_course: bool, is_new_module: bool, board: string, previous_board: string|null, previous_version: int|null, module_changed: bool, new: list<string>, changed: list<string>, removed: list<string>, unchanged: list<string>}|null  $diff
     */
    public function __construct(
        public ValidationReport $report,
        public ?array $diff = null,
    ) {}

    public function isValid(): bool
    {
        return $this->report->isValid();
    }

    public function hasChanges(): bool
    {
        if ($this->diff === null) {
            return false;
        }

        return $this->diff['is_new_module']
            || $this->diff['module_changed']
            || $this->diff['new'] !== []
            || $this->diff['changed'] !== []
            || $this->diff['removed'] !== [];
    }
}
