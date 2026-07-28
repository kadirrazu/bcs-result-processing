<?php

namespace App\Http\Controllers;

use App\Reports\Pdf\MasterDataPdfReport;
use App\Services\MasterDataExport\MasterDataExcelExportService;
use App\Services\MasterDataExport\MasterDataExportDefinition;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Download complete central master tables as editable Excel or print-ready PDF. */
final class MasterDataExportController extends Controller
{
    public function excel(string $type, MasterDataExcelExportService $service): BinaryFileResponse
    {
        $definition = MasterDataExportDefinition::resolve($type);
        $this->authorize('viewAny', $definition->model());

        $export = $service->generate($definition);

        return response()->download($export['path'], $export['filename'])->deleteFileAfterSend(true);
    }

    public function pdf(string $type, MasterDataPdfReport $report): Response
    {
        $definition = MasterDataExportDefinition::resolve($type);
        $this->authorize('viewAny', $definition->model());

        $export = $report->generate($definition);

        return response($export['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$export['filename'].'"',
            'Content-Length' => (string) strlen($export['content']),
        ]);
    }
}
