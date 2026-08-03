<?php

namespace App\Services\Viva;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** Generates the two authoritative Viva source templates. */
final class VivaTemplateService
{
    public function createMappingTemplate(string $path): void
    {
        $this->write($path, (array) config('viva.mapping_headers', []), [
            'CSQTFFHD', '11145695', '000123',
        ]);
    }

    public function createBoardTemplate(string $path): void
    {
        $this->write($path, (array) config('viva.board_headers', []), [
            '110526', '12', '000123', '050', '2', '', '', '', '',
        ]);
    }

    private function write(string $path, array $headers, array $sample): void
    {
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        $writer->addRow(Row::fromValues($sample));
        $writer->close();
    }
}
