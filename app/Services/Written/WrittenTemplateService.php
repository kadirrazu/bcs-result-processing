<?php

namespace App\Services\Written;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Generates the authoritative Written import template. */
final class WrittenTemplateService
{
    public function create(string $path): void
    {
        $headers = (array) config('written.headers', []);

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        $writer->addRow(Row::fromValues([
            'CSQTFFHD', '11145695', '62', '', '125', '143', '71', '31', '35', '66', '341', '152', '',
        ]));
        $writer->close();
    }
}
