<?php

namespace App\Services\MasterDataExport;

use BackedEnum;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Generate an import-compatible, editable XLSX export of a master table. */
final class MasterDataExcelExportService
{
    public function __construct(private readonly MasterDataExportQuery $query) {}

    public function generate(MasterDataExportDefinition $definition): array
    {
        $records = $this->query->execute($definition);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(Str::limit($definition->label(), 31, ''));

        foreach ($definition->headers() as $columnIndex => $header) {
            $sheet->setCellValue([$columnIndex + 1, 1], $header);
        }

        foreach ($records as $rowIndex => $record) {
            foreach ($definition->headers() as $columnIndex => $attribute) {
                $value = $record->getAttribute($attribute);
                $value = $value instanceof BackedEnum ? $value->value : $value;
                $value = is_bool($value) ? (int) $value : $value;

                // Codes are written explicitly as text so leading zeroes survive editing.
                if (in_array($attribute, ['subject_code', 'cadre_abbr'], true)) {
                    $sheet->setCellValueExplicit([$columnIndex + 1, $rowIndex + 2], (string) $value, DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue([$columnIndex + 1, $rowIndex + 2], $value);
                }
            }
        }

        $lastColumn = count($definition->headers());
        $lastRow = max(1, $records->count() + 1);
        $headerRange = 'A1:'.$sheet->getCell([$lastColumn, 1])->getCoordinate();
        $dataRange = 'A1:'.$sheet->getCell([$lastColumn, $lastRow])->getCoordinate();

        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF206BC4');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setAutoFilter($dataRange);
        $sheet->freezePane('A2');

        foreach (range(1, $lastColumn) as $column) {
            $sheet->getColumnDimensionByColumn($column)->setAutoSize(true);
        }

        $filename = Str::slug($definition->label()).'-'.now()->format('Ymd-His').'.xlsx';
        $path = storage_path('app/private/exports/'.$filename);
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return ['path' => $path, 'filename' => $filename];
    }
}
