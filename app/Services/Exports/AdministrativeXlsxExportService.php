<?php

namespace App\Services\Exports;

use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Streams administrative XLSX exports without loading the complete dataset into memory.
 */
final class AdministrativeXlsxExportService
{
    /**
     * @param array<string, scalar|null> $summary
     * @param list<string> $headers
     * @param iterable<array<int, scalar|null>> $rows
     */
    public function create(string $path, array $summary, array $headers, iterable $rows): int
    {
        File::ensureDirectoryExists(dirname($path));

        $writer = new Writer();
        $writer->openToFile($path);

        $writer->getCurrentSheet()->setName('Summary');
        $writer->addRow(Row::fromValues(['Export Summary', 'Value']));
        foreach ($summary as $label => $value) {
            $writer->addRow(Row::fromValues([(string) $label, $this->cell($value)]));
        }

        $dataSheet = $writer->addNewSheetAndMakeItCurrent();
        $dataSheet->setName('Data');
        $writer->addRow(Row::fromValues($headers));

        $count = 0;
        $buffer = [];
        $batchSize = max(100, (int) config('exports.xlsx_write_batch_size', 2000));

        foreach ($rows as $row) {
            $values = [];
            foreach ($row as $value) {
                $values[] = $this->cell($value);
            }

            $buffer[] = Row::fromValues($values);
            $count++;

            if (count($buffer) >= $batchSize) {
                $writer->addRows($buffer);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $writer->addRows($buffer);
        }

        $writer->close();

        return $count;
    }

    private function cell(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return $value;
    }
}
