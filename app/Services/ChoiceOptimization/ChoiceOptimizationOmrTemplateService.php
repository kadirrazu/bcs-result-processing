<?php

namespace App\Services\ChoiceOptimization;

use OpenSpout\Writer\XLSX\Writer;
use OpenSpout\Common\Entity\Row;

final class ChoiceOptimizationOmrTemplateService
{
    public function create(string $path): void
    {
        $max = max(1, (int) config('choice-optimization.omr_max_choices', 20));
        $headers = ['reg', 'change_choice'];
        for ($i = 1; $i <= $max; $i++) {
            $headers[] = 'opt_'.str_pad((string) $i, 2, '0', STR_PAD_LEFT);
        }

        $writer = new Writer();
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));
        $writer->close();
    }
}
