<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessAllocationVerificationPdfExport;
use App\Models\AllocationA5Run;
use App\Models\ReportingExportRun;
use App\Models\User;
use App\Services\Allocation\AllocationA6ReadinessService;
use App\Services\Allocation\AllocationResultDispositionService;
use App\Services\Reporting\AllocationVerificationReportService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class CadreSectionReportingController extends Controller
{
    private const EXPORT_MODULE = 'cadre_verification';

    public function index(AllocationA6ReadinessService $readiness): View
    {
        return view('reporting.cadre-section.index', [
            'gate' => $readiness->inspect(),
        ]);
    }

    public function generalCadreWiseIndex(
        AllocationA6ReadinessService $readiness,
        AllocationVerificationReportService $reports,
        ExaminationContext $context,
    ): View {
        $a5 = $readiness->requireReady();

        return view('reporting.cadre-section.cadre-wise-index', [
            'examination' => $context->current(),
            'kind' => 'general',
            'title' => 'General Cadre-wise Allocation Verification Reports',
            'description' => 'Every report includes the current ACTIVE General Merit candidates whose exact Allocation-ready Choice contains the selected General cadre.',
            'cadres' => $reports->generalCadres($a5),
            'searchPlaceholder' => 'e.g. 125 or PLIC',
            'filterLabel' => 'All General Cadres',
            'emptyMessage' => 'No current General cadre Allocation-ready Choice evidence found.',
        ]);
    }

    public function technicalCadreWiseIndex(
        AllocationA6ReadinessService $readiness,
        AllocationVerificationReportService $reports,
        ExaminationContext $context,
    ): View {
        $a5 = $readiness->requireReady();

        return view('reporting.cadre-section.cadre-wise-index', [
            'examination' => $context->current(),
            'kind' => 'technical',
            'title' => 'Technical Cadre-wise Allocation Verification Reports',
            'description' => 'Every report includes all candidates in the finalized cadre-specific merit-eligible population.',
            'cadres' => $reports->technicalCadres($a5),
            'searchPlaceholder' => 'e.g. 540 or MEDI',
            'filterLabel' => 'All Technical Cadres',
            'emptyMessage' => 'No current technical cadre merit evidence found.',
        ]);
    }

    public function verification(
        string $type,
        AllocationA6ReadinessService $readiness,
        AllocationVerificationReportService $reports,
        ExaminationContext $context,
    ): View {
        $a5 = $readiness->requireReady();
        $data = $reports->build($type, $a5);

        return view('reporting.allocation-verification.report', $data + [
            'examination' => $context->current(),
            'reportType' => $type,
            'cadreCode' => null,
        ]);
    }

    public function generalCadre(
        int $cadreCode,
        AllocationA6ReadinessService $readiness,
        AllocationVerificationReportService $reports,
        ExaminationContext $context,
    ): View {
        $a5 = $readiness->requireReady();
        $data = $reports->build('general-cadre', $a5, $cadreCode);

        return view('reporting.allocation-verification.report', $data + [
            'examination' => $context->current(),
            'reportType' => 'general-cadre',
            'cadreCode' => $cadreCode,
        ]);
    }

    public function technicalCadre(
        int $cadreCode,
        AllocationA6ReadinessService $readiness,
        AllocationVerificationReportService $reports,
        ExaminationContext $context,
    ): View {
        $a5 = $readiness->requireReady();
        $data = $reports->build('technical-cadre', $a5, $cadreCode);

        return view('reporting.allocation-verification.report', $data + [
            'examination' => $context->current(),
            'reportType' => 'technical-cadre',
            'cadreCode' => $cadreCode,
        ]);
    }

    public function cadreSerialMerit(
        AllocationA6ReadinessService $readiness,
        AllocationVerificationReportService $reports,
        ExaminationContext $context,
    ): View {
        $a5 = $readiness->requireReady();
        $data = $reports->buildCadreSerialMerit($a5);

        return view('reporting.allocation-verification.cadre-serial-merit', $data + [
            'examination' => $context->current(),
        ]);
    }

    public function queueCadreSerialMeritPdf(
        Request $request,
        AllocationA6ReadinessService $readiness,
        AllocationResultDispositionService $dispositions,
        ExaminationContext $context,
    ): RedirectResponse {
        return $this->queuePdf(
            $request,
            'cadre-serial-merit',
            null,
            $readiness,
            $dispositions,
            $context
        );
    }

    public function queueVerificationPdf(
        Request $request,
        string $type,
        AllocationA6ReadinessService $readiness,
        AllocationResultDispositionService $dispositions,
        ExaminationContext $context,
    ): RedirectResponse {
        abort_unless(in_array($type, ['common', 'general', 'technical-only'], true), 404);

        return $this->queuePdf($request, $type, null, $readiness, $dispositions, $context);
    }

    public function queueGeneralCadrePdf(
        Request $request,
        int $cadreCode,
        AllocationA6ReadinessService $readiness,
        AllocationResultDispositionService $dispositions,
        ExaminationContext $context,
    ): RedirectResponse {
        return $this->queuePdf($request, 'general-cadre', $cadreCode, $readiness, $dispositions, $context);
    }

    public function queueTechnicalCadrePdf(
        Request $request,
        int $cadreCode,
        AllocationA6ReadinessService $readiness,
        AllocationResultDispositionService $dispositions,
        ExaminationContext $context,
    ): RedirectResponse {
        return $this->queuePdf($request, 'technical-cadre', $cadreCode, $readiness, $dispositions, $context);
    }

    public function exportRun(ReportingExportRun $exportRun): View
    {
        $this->assertVerificationRun($exportRun);

        $generatedByUser = $exportRun->generated_by
            ? User::query()->find((int) $exportRun->generated_by)
            : null;

        return view('reporting.allocation-verification.export-show', [
            'run' => $exportRun,
            'generatedByUser' => $generatedByUser,
            'outdated' => $exportRun->status === 'completed' && ! $this->isSnapshotCurrent($exportRun),
        ]);
    }

    public function exportStatus(ReportingExportRun $exportRun): JsonResponse
    {
        $this->assertVerificationRun($exportRun);
        $exportRun->refresh();

        $outdated = $exportRun->status === 'completed' && ! $this->isSnapshotCurrent($exportRun);

        return response()->json([
            'status' => $outdated ? 'outdated' : $exportRun->status,
            'phase' => $exportRun->phase,
            'progress_percent' => (int) $exportRun->progress_percent,
            'progress_current' => (int) $exportRun->progress_current,
            'progress_total' => (int) $exportRun->progress_total,
            'progress_message' => $exportRun->progress_message,
            'failure_message' => $exportRun->failure_message,
            'finished' => $exportRun->isFinished(),
            'download_url' => $exportRun->status === 'completed' && ! $outdated
                ? route('examination-reports.cadre.verification.exports.download', $exportRun)
                : null,
        ]);
    }

    public function download(
        ReportingExportRun $exportRun,
        AllocationResultDispositionService $dispositions,
    ): BinaryFileResponse {
        $this->assertVerificationRun($exportRun);
        abort_unless($exportRun->status === 'completed', 409, 'PDF export is not ready for download.');

        $snapshot = (array) $exportRun->source_snapshot;
        $a5 = AllocationA5Run::query()->find((int) ($snapshot['allocation_a5_run_id'] ?? 0));
        abort_unless($a5, 409, 'Export source A5 is unavailable.');

        $current = $dispositions->snapshot($a5);
        $expectedHash = (string) ($snapshot['disposition_hash'] ?? '');

        abort_if(
            $expectedHash === '' || ! hash_equals($expectedHash, (string) $current['hash']),
            409,
            'This verification PDF is OUTDATED because A5.5 publication status changed. Regenerate it before download.'
        );

        abort_unless($exportRun->file_path && File::isFile($exportRun->file_path), 404, 'Generated PDF file is missing.');

        return response()->download(
            $exportRun->file_path,
            (string) $exportRun->file_name,
            ['Content-Type' => 'application/pdf']
        );
    }

    private function queuePdf(
        Request $request,
        string $type,
        ?int $cadreCode,
        AllocationA6ReadinessService $readiness,
        AllocationResultDispositionService $dispositions,
        ExaminationContext $context,
    ): RedirectResponse {
        $a5 = $readiness->requireReadyStrict();
        $examinationId = $context->currentId();
        abort_if($examinationId === null, 409, 'No examination is selected.');

        $disposition = $dispositions->snapshot($a5);

        $run = ReportingExportRun::query()->create([
            'module' => self::EXPORT_MODULE,
            'export_type' => 'PDF',
            'scope' => $type,
            'status' => 'queued',
            'phase' => 'QUEUED',
            'progress_percent' => 0,
            'progress_current' => 0,
            'progress_total' => 0,
            'progress_message' => 'Waiting for the centralized export queue.',
            'parameters' => [
                'report_type' => $type,
                'cadre_code' => $cadreCode,
                'page_size' => $type === 'cadre-serial-merit' ? 'A4' : 'Legal',
                'orientation' => 'Landscape',
                'margin_inches' => 0.5,
            ],
            'source_snapshot' => [
                'allocation_a5_run_id' => (int) $a5->id,
                'allocation_a4_run_id' => (int) $a5->allocation_a4_run_id,
                'a4_output_hash' => (string) $a5->a4_output_hash,
                'a5_candidate_hash' => (string) $a5->candidate_result_hash,
                'a5_capacity_hash' => (string) $a5->capacity_result_hash,
                'a5_finalized_at' => $a5->finalized_at?->toIso8601String(),
                'disposition_revision' => (int) $disposition['revision'],
                'disposition_hash' => (string) $disposition['hash'],
            ],
            'generated_by' => $request->user()?->id,
            'queued_at' => now(),
        ]);

        ProcessAllocationVerificationPdfExport::dispatch(
            $examinationId,
            (int) $run->id,
            (int) $a5->id,
            $run->generated_by,
        );

        return redirect()
            ->route('examination-reports.cadre.verification.exports.show', $run)
            ->with('success', 'Verification PDF generation queued. Progress will update automatically.');
    }

    private function isSnapshotCurrent(ReportingExportRun $run): bool
    {
        $snapshot = (array) $run->source_snapshot;
        $a5 = AllocationA5Run::query()->find((int) ($snapshot['allocation_a5_run_id'] ?? 0));

        if (! $a5) return false;
        if ((int) $a5->allocation_a4_run_id !== (int) ($snapshot['allocation_a4_run_id'] ?? 0)) return false;
        if (! hash_equals((string) ($snapshot['a5_candidate_hash'] ?? ''), (string) $a5->candidate_result_hash)) return false;
        if (! hash_equals((string) ($snapshot['a5_capacity_hash'] ?? ''), (string) $a5->capacity_result_hash)) return false;

        $current = app(AllocationResultDispositionService::class)->snapshot($a5);
        $hash = (string) ($snapshot['disposition_hash'] ?? '');

        return $hash !== '' && hash_equals($hash, (string) $current['hash']);
    }

    private function assertVerificationRun(ReportingExportRun $run): void
    {
        abort_unless($run->module === self::EXPORT_MODULE && $run->export_type === 'PDF', 404);
    }
}
