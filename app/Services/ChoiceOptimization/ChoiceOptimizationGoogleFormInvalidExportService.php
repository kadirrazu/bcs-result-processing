<?php
namespace App\Services\ChoiceOptimization;
use App\Models\ChoiceOptimizationGoogleFormBatch;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
final class ChoiceOptimizationGoogleFormInvalidExportService
{
    public function create(ChoiceOptimizationGoogleFormBatch $batch,string $path): int
    {
        $writer=new Writer(); $writer->openToFile($path); $writer->addRow(Row::fromValues(['reg','bcs','cadre','validation_errors'])); $count=0;
        $batch->rows()->where('validation_status','invalid')->orderBy('source_row')->chunk(500,function($rows) use($writer,&$count){ foreach($rows as $row){ $messages=collect((array)$row->validation_errors)->pluck('message')->implode(' | '); $writer->addRow(Row::fromValues([$row->raw_reg,$row->raw_bcs,$row->raw_cadre,$messages])); $count++; }});
        $writer->close(); return $count;
    }
}
