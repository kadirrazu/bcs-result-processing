<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDynamicQueryPdfExport;
use App\Jobs\ProcessDynamicQueryXlsxExport;
use App\Models\ReportingExportRun;
use App\Services\Reporting\DynamicQuery\DynamicQueryCompiler;
use App\Services\Reporting\DynamicQuery\DynamicReportAuthority;
use App\Services\Reporting\DynamicQuery\SavedDynamicReportService;
use App\Services\Reporting\DynamicQuery\SemanticFieldRegistry;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\View\View;

final class DynamicQueryBuilderController extends Controller
{
    public function index(
        ExaminationContext $context,
        SemanticFieldRegistry $registry,
        DynamicReportAuthority $authority,
        SavedDynamicReportService $savedReports,
    ): View {
        return view('reporting.dynamic-query.index', [
            'examination' => $context->current(),
            'semanticFields' => $registry->browserFields($authority->browserReadySources()),
            'previewSizes' => config('dynamic-reports.preview_sizes', [5, 10, 20, 50, 100]),
            'defaultPreviewSize' => config('dynamic-reports.default_preview_size', 10),
            'savedReports' => $savedReports->list(),
            'recentRuns' => $savedReports->recentRuns(),
        ]);
    }

    public function preview(
        Request $request,
        DynamicQueryCompiler $compiler,
        SavedDynamicReportService $savedReports,
    ): JsonResponse {
        $definition = $this->validatedDefinition($request);
        $savedReportId = $request->integer('saved_report_id') ?: null;
        $startedAt = hrtime(true);

        try {
            $result = $compiler->preview($definition);
        } catch (QueryException $exception) {
            $reference = 'DQ-'.strtoupper(substr(hash('sha256', now()->format('c').$exception->getMessage()), 0, 10));
            Log::error('Dynamic Query preview database failure.', [
                'error_reference' => $reference,
                'definition' => $definition,
                'exception' => $exception,
            ]);

            $sqlState = isset($exception->errorInfo[0]) ? (string) $exception->errorInfo[0] : null;
            $driverCode = isset($exception->errorInfo[1]) ? (string) $exception->errorInfo[1] : null;

            return response()->json([
                'message' => 'Preview query failed while evaluating the selected semantic fields. The error reference can be matched with the server log for the exact technical failure.',
                'error_reference' => $reference,
                'error_type' => 'database_query',
                'database_error_code' => $sqlState,
                'driver_error_code' => $driverCode,
            ], 500);
        } catch (Throwable $exception) {
            $reference = 'DQ-'.strtoupper(substr(hash('sha256', now()->format('c').$exception->getMessage()), 0, 10));
            Log::error('Dynamic Query preview failure.', [
                'error_reference' => $reference,
                'definition' => $definition,
                'exception' => $exception,
            ]);

            return response()->json([
                'message' => 'Preview failed while processing the semantic report definition.',
                'error_reference' => $reference,
                'error_type' => 'preview_processing',
            ], 500);
        }

        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($savedReportId) {
            $savedReports->recordPreview(
                $savedReportId,
                $definition,
                (int) $result['count'],
                count((array) $result['rows']),
                $durationMs,
                $request->user()?->getAuthIdentifier(),
            );
        }

        $result['duration_ms'] = $durationMs;

        return response()->json($result);
    }

    public function showSaved(int $savedReport, SavedDynamicReportService $savedReports): JsonResponse
    {
        return response()->json($savedReports->load($savedReport));
    }

    public function save(Request $request, SavedDynamicReportService $savedReports): JsonResponse
    {
        $payload = $request->validate([
            'id' => ['nullable', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:1000'],
            'definition' => ['required', 'array'],
        ]);

        $report = $savedReports->save(
            isset($payload['id']) ? (int) $payload['id'] : null,
            (string) $payload['name'],
            isset($payload['description']) ? (string) $payload['description'] : null,
            (array) $payload['definition'],
            $request->user()?->getAuthIdentifier(),
        );

        return response()->json(['report' => $report, 'saved_reports' => $savedReports->list()]);
    }

    public function destroySaved(int $savedReport, SavedDynamicReportService $savedReports): JsonResponse
    {
        $savedReports->delete($savedReport);

        return response()->json(['ok' => true, 'saved_reports' => $savedReports->list()]);
    }

    public function history(Request $request, SavedDynamicReportService $savedReports): JsonResponse
    {
        $savedReportId = $request->integer('saved_report_id') ?: null;
        return response()->json(['runs' => $savedReports->recentRuns($savedReportId)]);
    }


    public function exportXlsx(
        Request $request,
        DynamicQueryCompiler $compiler,
        ExaminationContext $context,
    ): JsonResponse {
        $definition = $this->validatedDefinition($request);
        $authority = $compiler->authoritySnapshot($definition);
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $run = ReportingExportRun::query()->create([
            'module' => 'dynamic_query',
            'export_type' => 'XLSX',
            'scope' => (string) ($definition['mode'] ?? 'detail'),
            'status' => 'queued',
            'phase' => 'QUEUED',
            'progress_percent' => 0,
            'progress_current' => 0,
            'progress_total' => 0,
            'progress_message' => 'Waiting for the centralized reporting export queue.',
            'parameters' => ['definition' => $definition, 'saved_report_id' => $request->integer('saved_report_id') ?: null],
            'source_snapshot' => array_merge(
                collect($authority)->except('warnings')->all(),
                ['definition_hash' => hash('sha256', json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')]
            ),
            'generated_by' => $request->user()?->getAuthIdentifier(),
            'queued_at' => now(),
        ]);

        ProcessDynamicQueryXlsxExport::dispatch($examinationId, (int) $run->id);

        return response()->json([
            'id' => (int) $run->id,
            'status_url' => route('examination-reports.dynamic-query.export.status', $run),
        ], 202);
    }


    public function exportPdf(
        Request $request,
        DynamicQueryCompiler $compiler,
        ExaminationContext $context,
    ): JsonResponse {
        $definition = $this->validatedDefinition($request);
        $authority = $compiler->authoritySnapshot($definition);
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $run = ReportingExportRun::query()->create([
            'module' => 'dynamic_query',
            'export_type' => 'PDF',
            'scope' => (string) ($definition['mode'] ?? 'detail'),
            'status' => 'queued',
            'phase' => 'QUEUED',
            'progress_percent' => 0,
            'progress_current' => 0,
            'progress_total' => 0,
            'progress_message' => 'Waiting for the centralized reporting export queue.',
            'parameters' => ['definition' => $definition, 'saved_report_id' => $request->integer('saved_report_id') ?: null],
            'source_snapshot' => array_merge(
                collect($authority)->except('warnings')->all(),
                ['definition_hash' => hash('sha256', json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '')]
            ),
            'generated_by' => $request->user()?->getAuthIdentifier(),
            'queued_at' => now(),
        ]);

        ProcessDynamicQueryPdfExport::dispatch($examinationId, (int) $run->id);

        return response()->json([
            'id' => (int) $run->id,
            'status_url' => route('examination-reports.dynamic-query.export.status', $run),
        ], 202);
    }

    public function exportStatus(ReportingExportRun $exportRun): JsonResponse
    {
        $this->assertDynamicExport($exportRun);
        $exportRun->refresh();
        return response()->json([
            'status' => $exportRun->status,
            'phase' => $exportRun->phase,
            'progress_percent' => (int) $exportRun->progress_percent,
            'progress_current' => (int) $exportRun->progress_current,
            'progress_total' => (int) $exportRun->progress_total,
            'progress_message' => $exportRun->progress_message,
            'failure_message' => $exportRun->failure_message,
            'finished' => $exportRun->isFinished(),
            'download_url' => $exportRun->status === 'completed'
                ? route('examination-reports.dynamic-query.export.download', $exportRun)
                : null,
        ]);
    }

    public function downloadExport(ReportingExportRun $exportRun): BinaryFileResponse
    {
        $this->assertDynamicExport($exportRun);
        abort_unless($exportRun->status === 'completed', 409, 'Dynamic Query export is not ready for download.');
        abort_unless($exportRun->file_path && File::isFile($exportRun->file_path), 404, 'Generated report file is missing.');

        return response()->download(
            $exportRun->file_path,
            (string) $exportRun->file_name,
            ['Content-Type' => (string) ($exportRun->file_mime ?: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')]
        );
    }

    private function assertDynamicExport(ReportingExportRun $run): void
    {
        abort_unless($run->module === 'dynamic_query' && in_array($run->export_type, ['XLSX', 'PDF'], true), 404);
    }

    /** @return array<string,mixed> */
    private function validatedDefinition(Request $request): array
    {
        return $request->validate([
            'mode' => ['nullable', 'in:detail,summary'],
            'fields' => ['required_if:mode,detail', 'array'], 'fields.*' => ['string'],
            // Conditions are a semantic tree: {boolean: and|or, rules:[condition|group...]}.
            // The compiler performs recursive whitelist/depth/node validation; no SQL fragment is accepted.
            'conditions' => ['sometimes', 'array'],
            'sorts' => ['sometimes', 'array'], 'sorts.*.field' => ['nullable', 'string'], 'sorts.*.direction' => ['nullable', 'in:asc,desc'],
            'groups' => ['sometimes', 'array'], 'groups.*' => ['string'],
            'aggregates' => ['sometimes', 'array'], 'aggregates.*.field' => ['nullable', 'string'], 'aggregates.*.function' => ['nullable', 'in:count,count_distinct,sum,avg,min,max'], 'aggregates.*.label' => ['nullable', 'string', 'max:120'], 'aggregates.*.sort_direction' => ['nullable', 'in:asc,desc'],
            'labels' => ['sometimes', 'array'], 'labels.*' => ['nullable', 'string', 'max:120'],
            'preview_size' => ['nullable', 'integer', Rule::in((array) config('dynamic-reports.preview_sizes', [5, 10, 20, 50, 100]))],
            'report_title' => ['nullable', 'string', 'max:180'],
            'show_serial' => ['nullable', 'boolean'], 'show_page_number' => ['nullable', 'boolean'], 'show_timestamp' => ['nullable', 'boolean'],
            'saved_report_id' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
