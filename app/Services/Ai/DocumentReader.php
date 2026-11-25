<?php

namespace App\Services\Ai;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Turns an uploaded document into chat content parts. A PDF is sent as-is (the model reads
 * text and scanned pages itself); a Word file is converted to Markdown here, since the model
 * does not accept .docx.
 */
class DocumentReader
{
    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @return list<array<string, mixed>>
     *
     * @throws RuntimeException when the document cannot be read.
     */
    public function contentParts(string $path, string $originalName): array
    {
        return match (strtolower(pathinfo($originalName, PATHINFO_EXTENSION))) {
            'pdf' => [$this->pdfPart($path, $originalName)],
            'docx' => [['type' => 'text', 'text' => "Isi dokumen Word `{$originalName}` (dikonversi ke Markdown):\n\n".$this->docxToMarkdown($path)]],
            default => throw new RuntimeException('Format dokumen tidak didukung. Pakai PDF atau Word (.docx).'),
        };
    }

    /**
     * @return array{type: string, file: array{filename: string, file_data: string}}
     */
    private function pdfPart(string $path, string $originalName): array
    {
        $bytes = (string) file_get_contents($path);

        if (! str_starts_with($bytes, '%PDF-')) {
            throw new RuntimeException('File bukan PDF yang valid.');
        }

        return ['type' => 'file', 'file' => ['filename' => $originalName, 'file_data' => 'data:application/pdf;base64,'.base64_encode($bytes)]];
    }

    /**
     * Keeps what matters for a module: headings, paragraphs, list items and tables.
     */
    public function docxToMarkdown(string $path): string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('File Word tidak bisa dibuka. Pastikan formatnya .docx (bukan .doc lama).');
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            throw new RuntimeException('File Word tidak berisi dokumen (word/document.xml tidak ada).');
        }

        $document = new DOMDocument;

        if (! $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Isi file Word rusak dan tidak bisa dibaca.');
        }

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('w', self::WORD_NS);
        $body = $xpath->query('/w:document/w:body')->item(0);

        if (! $body instanceof DOMElement) {
            throw new RuntimeException('Isi file Word kosong.');
        }

        $blocks = $this->blocks($body, $xpath);
        $markdown = trim(implode("\n\n", array_filter($blocks, fn (string $block): bool => $block !== '')));

        if ($markdown === '') {
            throw new RuntimeException('File Word tidak berisi teks.');
        }

        return $markdown;
    }

    /**
     * @return list<string>
     */
    private function blocks(DOMElement $parent, DOMXPath $xpath): array
    {
        $blocks = [];

        foreach ($parent->childNodes as $node) {
            if (! $node instanceof DOMElement || $node->namespaceURI !== self::WORD_NS) {
                continue;
            }

            $blocks = match ($node->localName) {
                'p' => [...$blocks, $this->paragraph($node, $xpath)],
                'tbl' => [...$blocks, $this->table($node, $xpath)],
                'sdt' => [...$blocks, ...$this->blocks($xpath->query('w:sdtContent', $node)->item(0) ?? $node, $xpath)],
                default => $blocks,
            };
        }

        return $blocks;
    }

    private function paragraph(DOMElement $paragraph, DOMXPath $xpath): string
    {
        $text = trim($this->text($paragraph, $xpath));

        if ($text === '') {
            return '';
        }

        $style = (string) $xpath->evaluate('string(w:pPr/w:pStyle/@w:val)', $paragraph);

        if (preg_match('/^(?:heading|judul)\s*([1-6])$/i', $style, $match) === 1) {
            return str_repeat('#', (int) $match[1]).' '.$text;
        }

        if (strcasecmp($style, 'Title') === 0) {
            return '# '.$text;
        }

        if ($xpath->query('w:pPr/w:numPr', $paragraph)->length > 0) {
            return '- '.$text;
        }

        return $text;
    }

    private function table(DOMElement $table, DOMXPath $xpath): string
    {
        $rows = [];

        foreach ($xpath->query('w:tr', $table) as $row) {
            $cells = [];

            foreach ($xpath->query('w:tc', $row) as $cell) {
                $paragraphs = [];

                foreach ($xpath->query('.//w:p', $cell) as $paragraph) {
                    $paragraphs[] = trim($this->text($paragraph, $xpath));
                }

                $cells[] = str_replace('|', '\|', trim(implode(' ', array_filter($paragraphs))));
            }

            $rows[] = '| '.implode(' | ', $cells).' |';

            if (count($rows) === 1) {
                $rows[] = '|'.str_repeat(' --- |', count($cells));
            }
        }

        return implode("\n", $rows);
    }

    private function text(DOMElement $paragraph, DOMXPath $xpath): string
    {
        $text = '';

        // Only run content: w:pPr also holds w:tab elements, but those are tab-stop definitions.
        foreach ($xpath->query('.//w:r/w:t | .//w:r/w:tab | .//w:r/w:br | .//w:r/w:cr', $paragraph) as $node) {
            $text .= match ($node->localName) {
                't' => $node->textContent,
                'tab' => "\t",
                default => "\n",
            };
        }

        return $text;
    }
}
