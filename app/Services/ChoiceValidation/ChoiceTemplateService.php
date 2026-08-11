<?php

namespace App\Services\ChoiceValidation;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

final class ChoiceTemplateService
{
    public function __construct(private readonly ChoiceColumnResolver $columns) {}

    public function create(string $path): void
    {
        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($this->columns->expectedHeaders()));
        $example = ['CSQTFFHD', '11145695'];
        foreach ($this->columns->choiceColumns() as $index => $column) {
            $example[] = $index < 4 ? [110, 115, 117, 121][$index] : null;
        }
        $writer->addRow(Row::fromValues($example));
        $writer->close();
    }
}
