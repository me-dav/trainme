<?php

namespace App\Services\Content;

/**
 * Result of validating a module file: blocking errors, non-blocking warnings,
 * and the normalised module definition when the file is valid.
 */
final class ValidationReport
{
    /** @var list<string> */
    public array $errors = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var array<string, mixed>|null */
    public ?array $definition = null;

    public function isValid(): bool
    {
        return $this->errors === [] && $this->definition !== null;
    }

    public function error(string $message): void
    {
        $this->errors[] = $message;
    }

    public function warning(string $message): void
    {
        $this->warnings[] = $message;
    }
}
