<?php

namespace App\Reports\Pdf;

use Illuminate\Support\Str;
use Mpdf\HTMLParserMode;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class DynamicQueryPdfReport
{
    /**
     * @param array<int,array{id:string,label:string}> $columns
     * @param iterable<int,array<string,mixed>> $rows
     * @return array{filename:string,processed:int}
     */
    public function generateToFile(
        string $path,
        array $definition,
        array $columns,
        iterable $rows,
        string $examinationName,
        ?callable $progress = null,
        int $total = 0,
        array $totals = [],
        ?string $totalLabel = null,
    ): array {
        $generatedAt = now();
        $showSerial = (bool) ($definition['show_serial'] ?? true);
        $showPage = (bool) ($definition['show_page_number'] ?? true);
        $showTime = (bool) ($definition['show_timestamp'] ?? true);
        $title = trim((string) ($definition['report_title'] ?? 'Dynamic Query Report')) ?: 'Dynamic Query Report';
        $exam = trim($examinationName) ?: 'Selected Examination';
        $columnCount = count($columns) + ($showSerial ? 1 : 0);
        $orientation = $columnCount > 6 ? 'L' : 'P';

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => $orientation,
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 22,
            'margin_bottom' => $showPage ? 12 : 10,
            'margin_header' => 5,
            'margin_footer' => 5,
            // Reuse the same proven shared mPDF temp location used by existing reports.
            'tempDir' => $this->tempDirectory(),
            'default_font' => 'dejavusans',
        ]);

        $mpdf->SetTitle($exam.' - '.$title);
        $mpdf->SetAuthor((string) config('app.name'));
        $header = '<div style="text-align:center;font-family:DejaVu Sans,sans-serif;line-height:1.3">'
            .'<div style="font-size:10pt"><strong>'.e($exam).'</strong></div>'
            .'<div style="font-size:12pt"><strong>'.e($title).'</strong></div>'
            .($showTime ? '<div style="font-size:7pt;color:#667085">Generated: '.e($generatedAt->format('d-m-Y h:i:s A')).'</div>' : '')
            .'</div>';
        $mpdf->SetHTMLHeader($header);

        if ($showPage) {
            $mpdf->SetHTMLFooter(
                '<div style="text-align:right;font-family:DejaVu Sans,sans-serif;font-size:7pt;color:#667085">'
                .'Page {PAGENO} of {nbpg}</div>'
            );
        }

        $mpdf->WriteHTML($this->styles($columnCount), HTMLParserMode::HEADER_CSS);

        $headers = $showSerial ? ['Sl.'] : [];
        foreach ($columns as $column) {
            $headers[] = (string) $column['label'];
        }

        $processed = 0;
        $pageChunk = [];
        $chunkSize = $orientation === 'L' ? 45 : 38;

        foreach ($rows as $data) {
            $values = [];
            if ($showSerial) {
                $values[] = (string) ($processed + 1);
            }
            foreach ($columns as $column) {
                $value = $data[$column['id']] ?? null;
                $values[] = ($value === null || $value === '') ? '—' : (string) $value;
            }
            $pageChunk[] = $values;
            $processed++;

            if (count($pageChunk) >= $chunkSize) {
                $this->writeTable($mpdf, $headers, $pageChunk, $processed > count($pageChunk));
                $pageChunk = [];
            }

            if ($progress && ($processed % 100 === 0 || ($total > 0 && $processed >= $total))) {
                $progress($processed, $total);
            }
        }

        if ($pageChunk !== []) {
            $this->writeTable($mpdf, $headers, $pageChunk, $processed > count($pageChunk));
        } elseif ($processed === 0) {
            $mpdf->WriteHTML('<div class="empty">No matching records.</div>', HTMLParserMode::HTML_BODY);
        }

        if ($totals !== [] && $totalLabel) {
            $footer = [];
            if ($showSerial) $footer[] = '';
            $labelPlaced = false;
            foreach ($columns as $column) {
                $id = (string) ($column['id'] ?? '');
                if (array_key_exists($id, $totals)) {
                    $footer[] = (string) ($totals[$id] ?? '');
                    continue;
                }
                if (! $labelPlaced) {
                    $footer[] = $totalLabel;
                    $labelPlaced = true;
                } else {
                    $footer[] = '';
                }
            }
            $html = '<table class="grand-total"><tbody><tr>';
            foreach ($footer as $value) {
                $html .= '<td><strong>'.e((string) $value).'</strong></td>';
            }
            $html .= '</tr></tbody></table>';
            $mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
        }

        $directory = dirname($path);
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException("PDF output directory [{$directory}] could not be created.");
        }

        // Write straight to the private export file. This avoids holding the complete PDF binary in PHP memory.
        $mpdf->Output($path, Destination::FILE);
        clearstatcache(true, $path);
        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('mPDF did not produce a readable PDF file.');
        }

        $slug = Str::slug($title) ?: 'dynamic-query-report';

        return [
            'filename' => $slug.'-'.$generatedAt->format('Ymd-His').'.pdf',
            'processed' => $processed,
        ];
    }

    /** @param array<int,string> $headers @param array<int,array<int,string>> $rows */
    private function writeTable(Mpdf $mpdf, array $headers, array $rows, bool $newPage): void
    {
        if ($newPage) {
            $mpdf->AddPage();
        }

        $html = '<table><thead><tr>';
        foreach ($headers as $header) {
            $html .= '<th>'.e($header).'</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($row as $value) {
                $html .= '<td>'.nl2br(e((string) $value)).'</td>';
            }
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';
        $mpdf->WriteHTML($html, HTMLParserMode::HTML_BODY);
    }

    private function styles(int $columnCount): string
    {
        $fontSize = $columnCount > 10 ? '6.4pt' : ($columnCount > 7 ? '7pt' : '8pt');

        return 'body{font-family:DejaVu Sans,sans-serif;color:#182433;font-size:'.$fontSize.'}'
            .'table{width:100%;border-collapse:collapse;table-layout:auto}th,td{border:0.25mm solid #c8ced6;padding:2.2mm 1.6mm;vertical-align:top;word-wrap:break-word}'
            .'th{font-weight:bold;text-align:center;background:#f1f3f5}.grand-total{margin-top:2mm}.grand-total td{background:#f8f9fa;font-weight:bold}.empty{text-align:center;color:#667085;padding:20mm 0}';
    }

    private function tempDirectory(): string
    {
        $path = storage_path('app/private/mpdf');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("mPDF temporary directory [{$path}] could not be created.");
        }
        if (! is_writable($path)) {
            throw new RuntimeException("mPDF temporary directory [{$path}] is not writable.");
        }

        return $path;
    }
}
