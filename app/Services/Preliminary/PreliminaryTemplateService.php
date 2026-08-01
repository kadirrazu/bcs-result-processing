<?php

namespace App\Services\Preliminary;

use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;

/** Generates the authoritative four-column preliminary import template. */
final class PreliminaryTemplateService
{
    public function create(string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['user', 'reg', 'mark', 'candidate_status']));
        $writer->addRow(Row::fromValues(['CSQTFFHD', '11145695', '72.50', '']));
        $writer->close();
    }
}
