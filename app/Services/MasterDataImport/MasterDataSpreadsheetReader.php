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

        $expectedHeaders = array_values(array_map(
            fn ($value) => $this->normalizeHeader($value),
            $expectedHeaders,
        ));

        $headerRow = array_shift($raw);
        $headers = array_values(array_map(
            fn ($value) => $this->normalizeHeader($value),
            is_array($headerRow) ? $headerRow : [],
        ));

        // PhpSpreadsheet may expose trailing empty cells if a workbook has a
        // formatted/styled used range beyond the visible header columns.
        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        if ($headers !== $expectedHeaders) {
            throw new RuntimeException(sprintf(
                'Spreadsheet headers do not match the downloaded template. Expected [%s]; found [%s]. Running project: %s',
                implode(', ', $expectedHeaders),
                implode(', ', $headers),
                base_path(),
            ));
        }

        $rows = [];

        foreach ($raw as $index => $values) {
            if (collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                continue;
            }

            $values = array_slice(
                array_pad(array_values($values), count($headers), null),
                0,
                count($headers),
            );

            $rows[] = [
                'row_number' => $index + 2,
                'data' => array_combine($headers, $values),
            ];
        }

        return $rows;
    }

    private function normalizeHeader(mixed $value): string
    {
        $value = (string) $value;

        // Remove UTF-8 BOM, zero-width characters and normalize NBSP before trim.
        $value = str_replace(["\xEF\xBB\xBF", "\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"], '', $value);
        $value = str_replace("\u{00A0}", ' ', $value);

        return mb_strtolower(trim($value));
    }
}
