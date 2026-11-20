<?php

namespace App\Services\Content;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Splits a module file into its YAML frontmatter and Markdown body.
 */
class ModuleParser
{
    /**
     * @return array{meta: array<string, mixed>, body: string}
     *
     * @throws ModuleParseException
     */
    public function parse(string $raw): array
    {
        $raw = (string) preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        if (preg_match('/\A---\n(.*?)\n---[ \t]*(?:\n(.*))?\z/s', $raw, $matches) !== 1) {
            throw new ModuleParseException('File harus diawali frontmatter YAML: baris pertama `---`, ditutup baris `---`.');
        }

        try {
            $meta = Yaml::parse($matches[1]);
        } catch (ParseException $exception) {
            throw new ModuleParseException('YAML frontmatter tidak valid: '.$exception->getMessage().' (baris dihitung dari setelah `---` pertama).');
        }

        if (! is_array($meta)) {
            throw new ModuleParseException('Frontmatter harus berupa mapping YAML (key: value).');
        }

        return ['meta' => $meta, 'body' => trim($matches[2] ?? '')];
    }
}
