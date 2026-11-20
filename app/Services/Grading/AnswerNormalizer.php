<?php

namespace App\Services\Grading;

/**
 * Turns free-typed answers into canonical strings so equivalent spellings compare equal.
 */
class AnswerNormalizer
{
    /**
     * Canonical form of a single shell command: prompt and leading bare `sudo` removed,
     * shell quoting and backslash escapes resolved, whitespace collapsed, redirections joined.
     */
    public function command(string $raw): string
    {
        return implode(' ', $this->commandTokens($raw));
    }

    /**
     * @return list<string>
     */
    public function commandTokens(string $raw): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $raw);
        $text = str_replace("\\\n", ' ', $text);
        $text = trim(str_replace("\n", ' ', $text));
        $text = $this->stripPromptAndSudo($text);

        return $this->joinRedirections($this->tokenize($text));
    }

    /**
     * Canonical form that ignores option order and splits bundled short flags (`-ld` = `-l -d`).
     * Positional arguments keep their order.
     *
     * @param  list<string>  $tokens
     */
    public function flagsCanonical(array $tokens): string
    {
        if ($tokens === []) {
            return '';
        }

        $command = array_shift($tokens);
        $options = [];
        $positionals = [];

        foreach ($tokens as $token) {
            if ($token === '--' || $token === '-' || ! str_starts_with($token, '-')) {
                $positionals[] = $token;
            } elseif (preg_match('/^-[A-Za-z]{2,}$/', $token) === 1) {
                foreach (str_split(substr($token, 1)) as $flag) {
                    $options[] = '-'.$flag;
                }
            } else {
                $options[] = $token;
            }
        }

        $options = array_values(array_unique($options));
        sort($options);

        return $command.' '.implode(' ', $options).' | '.implode(' ', $positionals);
    }

    /**
     * Canonical form of a short text answer: case-insensitive, trimmed, no wrapping quotes or trailing dot.
     */
    public function text(string $raw): string
    {
        $text = mb_strtolower(trim($raw));
        $text = trim($text, " \t\n\r\0\x0B`'\"");
        $text = rtrim($text, '.');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Normalised multi-line answer for scenario checklists: one command per line,
     * prompts and bare `sudo` removed, runs of spaces collapsed.
     */
    public function scenario(string $raw): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $raw));
        $normalized = [];

        foreach ($lines as $line) {
            $line = trim((string) preg_replace('/[ \t]+/', ' ', $line));

            if ($line === '') {
                continue;
            }

            $normalized[] = $this->stripPromptAndSudo($line);
        }

        return implode("\n", $normalized);
    }

    private function stripPromptAndSudo(string $text): string
    {
        $text = (string) preg_replace('/^\$\s+/', '', $text);

        return (string) preg_replace('/^sudo\s+(?!-)/', '', $text);
    }

    /**
     * Minimal POSIX-shell word splitting: quotes are removed, backslash escapes resolved.
     *
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $tokens = [];
        $buffer = '';
        $inToken = false;
        $quote = null;
        $length = strlen($text);

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            if ($quote === "'") {
                if ($char === "'") {
                    $quote = null;
                } else {
                    $buffer .= $char;
                }

                continue;
            }

            if ($quote === '"') {
                if ($char === '"') {
                    $quote = null;
                } elseif ($char === '\\' && $i + 1 < $length && str_contains('"\\$`', $text[$i + 1])) {
                    $buffer .= $text[++$i];
                } else {
                    $buffer .= $char;
                }

                continue;
            }

            if ($char === ' ' || $char === "\t") {
                if ($inToken) {
                    $tokens[] = $buffer;
                    $buffer = '';
                    $inToken = false;
                }

                continue;
            }

            $inToken = true;

            if ($char === "'" || $char === '"') {
                $quote = $char;
            } elseif ($char === '\\' && $i + 1 < $length) {
                $buffer .= $text[++$i];
            } else {
                $buffer .= $char;
            }
        }

        if ($inToken) {
            $tokens[] = $buffer;
        }

        return $tokens;
    }

    /**
     * Joins a bare redirection operator with its target: `2> /dev/null` becomes `2>/dev/null`.
     *
     * @param  list<string>  $tokens
     * @return list<string>
     */
    private function joinRedirections(array $tokens): array
    {
        $joined = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            if (preg_match('/^\d*(>>?|<)$/', $tokens[$i]) === 1 && $i + 1 < $count) {
                $joined[] = $tokens[$i].$tokens[++$i];
            } else {
                $joined[] = $tokens[$i];
            }
        }

        return $joined;
    }
}
