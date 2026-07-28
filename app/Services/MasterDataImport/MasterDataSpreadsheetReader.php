<?php

namespace App\Services\MasterDataImport;

use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

/** Read the first worksheet into normalized associative rows. */
final class MasterDataSpreadsheetReader
{
    public function read(string $path, array $expectedHeaders): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $raw = $sheet->toArray(null, true, true, false);
        if ($raw === []) {
            throw new RuntimeException('The spreadsheet is empty.');
        }

        $headers = array_map(fn ($value) => strtolower(trim((string) $value)), array_shift($raw));
        if ($headers !== $expectedHeaders) {
            throw new RuntimeException('Spreadsheet headers do not match the downloaded template.');
        }

        $rows = [];
        foreach ($raw as $index => $values) {
            if (collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                continue;
            }
            $rows[] = ['row_number' => $index + 2, 'data' => array_combine($headers, array_slice(array_pad($values, count($headers), null), 0, count($headers)))];
        }

        return $rows;
    }
}
