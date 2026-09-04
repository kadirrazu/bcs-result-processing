<?php

namespace App\Services\Documents;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Fills simple [[PLACEHOLDER]] markers in DOCX paragraphs while preserving the
 * surrounding paragraph and the first run's formatting. Placeholders may be
 * split across multiple Word runs. Main document, headers and footers are read.
 */
final class DocxPlaceholderTemplateService
{
    /**
     * @param array<string, string> $replacements Keys without [[ ]].
     * @return array{replaced: array<string,int>, total_replacements:int, unknown_placeholders: array<int,string>}
     */
    public function fill(string $templatePath, string $outputPath, array $replacements, array $literalReplacements = []): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZIP extension is required to create a Word publishing document.');
        }

        if (! class_exists(DOMDocument::class)) {
            throw new RuntimeException('The PHP DOM extension is required to create a Word publishing document.');
        }

        if (! is_file($templatePath)) {
            throw new RuntimeException('The selected Word template could not be read.');
        }

        $directory = dirname($outputPath);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('The publishing document folder could not be created.');
        }

        if (! copy($templatePath, $outputPath)) {
            throw new RuntimeException('The Word template could not be copied for processing.');
        }

        $zip = new ZipArchive();
        $open = $zip->open($outputPath);
        if ($open !== true) {
            @unlink($outputPath);
            throw new RuntimeException('The selected file is not a readable DOCX document.');
        }

        $parts = ['word/document.xml'];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = (string) $zip->getNameIndex($i);
            if (preg_match('#^word/(header|footer)\d*\.xml$#', $name) === 1) {
                $parts[] = $name;
            }
        }
        $parts = array_values(array_unique($parts));

        $counts = array_fill_keys(array_keys($replacements), 0);
        $unknown = [];

        foreach ($parts as $part) {
            $xml = $zip->getFromName($part);
            if (! is_string($xml) || $xml === '') {
                continue;
            }

            [$processedXml, $partCounts, $partUnknown] = $this->processXml($xml, $replacements, $literalReplacements);
            foreach ($partCounts as $key => $count) {
                $counts[$key] = ($counts[$key] ?? 0) + $count;
            }
            $unknown = [...$unknown, ...$partUnknown];

            if (! $zip->addFromString($part, $processedXml)) {
                $zip->close();
                @unlink($outputPath);
                throw new RuntimeException('The Word template could not be updated.');
            }
        }

        $zip->close();

        return [
            'replaced' => $counts,
            'total_replacements' => array_sum($counts),
            'unknown_placeholders' => array_values(array_unique($unknown)),
        ];
    }

    /**
     * @param array<string, string> $replacements
     * @return array{0:string,1:array<string,int>,2:array<int,string>}
     */
    private function processXml(string $xml, array $replacements, array $literalReplacements = []): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = true;
        $dom->formatOutput = false;

        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new RuntimeException('A Word document XML part could not be read.');
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $counts = array_fill_keys(array_keys($replacements), 0);
        $paragraphs = [];
        foreach ($xpath->query('//w:p') ?: [] as $paragraph) {
            if ($paragraph instanceof DOMElement) {
                $paragraphs[] = $paragraph;
            }
        }

        foreach ($paragraphs as $paragraph) {
            $textNodes = $xpath->query('.//w:t', $paragraph);
            if ($textNodes === false || $textNodes->length === 0) {
                continue;
            }

            $fullText = '';
            foreach ($textNodes as $textNode) {
                $fullText .= $textNode->textContent;
            }

            $newText = $fullText;
            $changed = false;
            foreach ($replacements as $key => $replacement) {
                $marker = '[['.$key.']]';
                $occurrences = substr_count($newText, $marker);
                if ($occurrences < 1) {
                    continue;
                }
                $newText = str_replace($marker, $replacement, $newText);
                $counts[$key] += $occurrences;
                $changed = true;
            }

            // Optional exact literal markers keep legacy double-bracket callers
            // unchanged while allowing publishing modules to support explicitly
            // contracted markers such as [REPORT_GENERATION_TIMESTAMP].
            foreach ($literalReplacements as $marker => $replacement) {
                $occurrences = substr_count($newText, (string) $marker);
                if ($occurrences < 1) {
                    continue;
                }
                $newText = str_replace((string) $marker, (string) $replacement, $newText);
                $changed = true;
            }

            if ($changed) {
                $this->replaceParagraphText($dom, $xpath, $paragraph, $newText);
            }
        }

        $remainingText = '';
        foreach ($xpath->query('//w:t') ?: [] as $node) {
            $remainingText .= $node->textContent.' ';
        }
        preg_match_all('/\[\[([A-Z0-9_]+)\]\]/', $remainingText, $matches);

        return [$dom->saveXML() ?: $xml, $counts, $matches[1] ?? []];
    }

    private function replaceParagraphText(DOMDocument $dom, DOMXPath $xpath, DOMElement $paragraph, string $text): void
    {
        $paragraphProperties = null;
        $firstRunProperties = null;

        $pPr = $xpath->query('./w:pPr', $paragraph)?->item(0);
        if ($pPr instanceof DOMNode) {
            $paragraphProperties = $pPr->cloneNode(true);
        }

        $firstRun = $xpath->query('./w:r', $paragraph)?->item(0);
        if ($firstRun instanceof DOMElement) {
            $rPr = $xpath->query('./w:rPr', $firstRun)?->item(0);
            if ($rPr instanceof DOMNode) {
                $firstRunProperties = $rPr->cloneNode(true);
            }
        }

        while ($paragraph->firstChild !== null) {
            $paragraph->removeChild($paragraph->firstChild);
        }

        if ($paragraphProperties !== null) {
            $paragraph->appendChild($paragraphProperties);
        }

        $run = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:r');
        if ($firstRunProperties !== null) {
            $run->appendChild($firstRunProperties);
        }

        $lines = preg_split('/\R/u', $text) ?: [''];
        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $run->appendChild($dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:br'));
            }
            $textNode = $dom->createElementNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'w:t');
            $textNode->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $textNode->appendChild($dom->createTextNode($line));
            $run->appendChild($textNode);
        }

        $paragraph->appendChild($run);
    }
}
