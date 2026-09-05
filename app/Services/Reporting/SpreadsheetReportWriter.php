<?php

namespace App\Services\Reporting;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Reusable XLSX writer. Domain/report modules supply headers and row values;
 * this service owns spreadsheet mechanics only.
 */
final class SpreadsheetReportWriter
{
    /**
     * @param array<int,string> $headers
     * @param iterable<int,array<int,mixed>> $rows
     * @param array<int,int> $textColumnIndexes 1-based Excel column indexes
     */
    public function write(
        string $path,
        array $headers,
        iterable $rows,
        array $textColumnIndexes = [],
        ?callable $progress = null,
        ?int $totalRows = null,
        string $sheetTitle = 'Report',
    ): void {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($sheetTitle, 0, 31));

        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:'.$lastColumn.'1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:'.$lastColumn.'1');

        $excelRow = 2;
        $processed = 0;
        foreach ($rows as $values) {
            $sheet->fromArray([$values], null, 'A'.$excelRow);
            foreach ($textColumnIndexes as $columnIndex) {
                if (! array_key_exists($columnIndex - 1, $values)) {
                    continue;
                }
                $sheet->setCellValueExplicit(
                    Coordinate::stringFromColumnIndex($columnIndex).$excelRow,
                    (string) ($values[$columnIndex - 1] ?? ''),
                    DataType::TYPE_STRING
                );
            }
            $excelRow++;
            $processed++;
            if ($progress && ($processed % 100 === 0 || ($totalRows !== null && $processed >= $totalRows))) {
                $progress($processed, $totalRows ?? $processed);
            }
        }

        for ($column = 1; $column <= count($headers); $column++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($column))->setAutoSize(true);
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();
    }
}
