<?php

namespace App\Services\MasterDataImport;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Generate a formatted, module-specific Excel template. */
final class MasterDataTemplateService
{
    public function download(MasterDataImportDefinition $definition): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import Template');
        $sheet->fromArray($definition->headers(), null, 'A1');
        $sheet->fromArray($definition->config['sample'], null, 'A2');
        $last = chr(64 + count($definition->headers()));
        $sheet->getStyle("A1:{$last}1")->getFont()->setBold(true);
        $sheet->freezePane('A2');
        foreach (range('A', $last) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $path = tempnam(sys_get_temp_dir(), 'master-template-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return response()->download($path, $definition->key.'-template.xlsx')->deleteFileAfterSend(true);
    }
}
