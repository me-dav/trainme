<?php

namespace App\Support;

use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Renders module Markdown: GitHub-flavoured tables, raw HTML escaped, external links in a new tab,
 * `> [!INSIGHT]`-style callouts, and h2 anchors for the table of contents.
 * Mermaid and syntax highlighting happen in the browser (resources/js/app.js).
 */
class MarkdownRenderer
{
    /** @var array<string, string> */
    public const CALLOUTS = [
        'NOTE' => 'Catatan',
        'TIP' => 'Tips',
        'WARNING' => 'Hati-hati',
        'DANGER' => 'Bahaya',
        'INSIGHT' => 'Insight lapangan',
    ];

    private ?MarkdownConverter $converter = null;

    /**
     * @return array{html: string, toc: list<array{id: string, title: string}>}
     */
    public function document(string $markdown): array
    {
        $html = $this->callouts($this->convert($markdown));
        $toc = [];
        $used = [];

        $html = (string) preg_replace_callback('~<h2>(.*?)</h2>~s', function (array $matches) use (&$toc, &$used): string {
            $title = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5);
            $id = Str::slug($title) ?: 'bagian';
            $base = $id;
            $suffix = 2;

            while (isset($used[$id])) {
                $id = $base.'-'.$suffix++;
            }

            $used[$id] = true;
            $toc[] = ['id' => $id, 'title' => $title];

            return '<h2 id="'.$id.'">'.$matches[1].'</h2>';
        }, $html);

        return ['html' => $html, 'toc' => $toc];
    }

    /**
     * Renders a short Markdown fragment such as a question prompt or explanation.
     */
    public function fragment(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }

        return $this->callouts($this->convert($markdown));
    }

    private function convert(string $markdown): string
    {
        return $this->converter()->convert($markdown)->getContent();
    }

    private function callouts(string $html): string
    {
        $pattern = '~<blockquote>\s*<p>\[!('.implode('|', array_keys(self::CALLOUTS)).')\]\s*~';

        $html = (string) preg_replace_callback($pattern, function (array $matches): string {
            $type = $matches[1];

            return '<blockquote class="callout callout-'.strtolower($type).'"><p class="callout-title">'.self::CALLOUTS[$type].'</p><p>';
        }, $html);

        return str_replace('<p></p>', '', $html);
    }

    private function converter(): MarkdownConverter
    {
        if ($this->converter !== null) {
            return $this->converter;
        }

        $environment = new Environment([
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
            'external_link' => [
                'internal_hosts' => [],
                'open_in_new_window' => true,
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new ExternalLinkExtension);

        return $this->converter = new MarkdownConverter($environment);
    }
}
