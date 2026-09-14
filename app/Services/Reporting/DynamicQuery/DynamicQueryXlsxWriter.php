<?php

namespace App\Services\Reporting\DynamicQuery;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class DynamicQueryXlsxWriter
{
    /** @param array<int,array{id:string,label:string}> $columns @param iterable<int,array<string,mixed>> $rows */
    public function write(string $path, array $definition, array $columns, iterable $rows, ?callable $progress = null, ?int $total = null, array $totals = [], ?string $totalLabel = null): void
    {
        $showSerial = (bool) ($definition['show_serial'] ?? true);
        $showPage = (bool) ($definition['show_page_number'] ?? true);
        $showTime = (bool) ($definition['show_timestamp'] ?? true);
        $title = trim((string) ($definition['report_title'] ?? 'Dynamic Query Report')) ?: 'Dynamic Query Report';

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');
        $headers = array_map(static fn (array $column): string => (string) $column['label'], $columns);
        if ($showSerial) array_unshift($headers, 'Sl.');
        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($headers)));

        $row = 1;
        $sheet->setCellValue('A'.$row, $title);
        $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
        $sheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
        $row++;
        if ($showTime) {
            $sheet->setCellValue('A'.$row, 'Generated: '.now()->format('d-m-Y h:i:s A'));
            $sheet->mergeCells('A'.$row.':'.$lastColumn.$row);
            $row++;
        }
        $headerRow = $row;
        $sheet->fromArray($headers, null, 'A'.$headerRow);
        $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)->getFont()->setBold(true);
        $sheet->setAutoFilter('A'.$headerRow.':'.$lastColumn.$headerRow);
        $sheet->freezePane('A'.($headerRow + 1));

        $processed = 0;
        foreach ($rows as $data) {
            $values = [];
            if ($showSerial) $values[] = $processed + 1;
            foreach ($columns as $column) $values[] = $data[$column['id']] ?? null;
            $sheet->fromArray([$values], null, 'A'.($headerRow + 1 + $processed));
            $processed++;
            if ($progress && ($processed % 100 === 0 || ($total !== null && $processed >= $total))) $progress($processed, $total ?? $processed);
        }

        if ($totals !== [] && $totalLabel) {
            $footerRow = $headerRow + 1 + $processed;
            $values = [];
            if ($showSerial) $values[] = '';
            $labelPlaced = false;
            foreach ($columns as $column) {
                $id = (string) ($column['id'] ?? '');
                if (array_key_exists($id, $totals)) {
                    $values[] = $totals[$id];
                    continue;
                }
                if (! $labelPlaced) {
                    $values[] = $totalLabel;
                    $labelPlaced = true;
                } else {
                    $values[] = '';
                }
            }
            $sheet->fromArray([$values], null, 'A'.$footerRow);
            $sheet->getStyle('A'.$footerRow.':'.$lastColumn.$footerRow)->getFont()->setBold(true);
        }

        for ($column = 1; $column <= count($headers); $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }
        if ($showPage) $sheet->getHeaderFooter()->setOddFooter('&CPage &P of &N');
        $sheet->getPageSetup()->setFitToWidth(1)->setFitToHeight(0);
        $sheet->getPageSetup()->setFitToPage(true);

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }
}
