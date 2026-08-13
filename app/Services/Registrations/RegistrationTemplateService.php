<?php

namespace App\Services\Registrations;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** Build the official template; fixed headers make validation deterministic. */
final class RegistrationTemplateService
{
    public function create(string $path): void
    {
        $headers = config('registrations.headers');
        $lastColumn = Coordinate::stringFromColumnIndex(count($headers));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Registrations');
        $sheet->fromArray($headers, null, 'A1');
        $sheet->fromArray([
            'U000000001', '12345678', 'Example Candidate', '', '', '02-05-1995',
            '001234', 2010, '005678', 2012, 2017,
            1, 1, '', 101, 201, 2, '', '', null, null, null, '1234567890',
            3, 'active', 'Sample row - delete before import',
        ], null, 'A2');
        $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');

        foreach (['ssc_roll', 'hsc_roll'] as $header) {
            $columnIndex = array_search($header, $headers, true);
            if ($columnIndex !== false) {
                $column = Coordinate::stringFromColumnIndex($columnIndex + 1);
                $sheet->getStyle($column.':'.$column)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_TEXT);
            }
        }

        for ($index = 1; $index <= count($headers); $index++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($index))->setAutoSize(true);
        }

        (new Xlsx($spreadsheet))->save($path);
    }
}
