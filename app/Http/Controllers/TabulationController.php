<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTabulation;
use App\Models\PreliminaryResult;
use App\Models\Registration;
use App\Models\TabulationFinalizationRun;
use App\Models\TabulationProcessingAudit;
use App\Models\TabulationProcessingRun;
use App\Models\TabulationProcessingState;
use App\Models\TabulationResult;
use App\Models\User;
use App\Models\VivaResult;
use App\Models\WrittenResult;
use App\Reports\Pdf\TabulationIndividualPdfReport;
use App\Services\Exports\AdministrativeXlsxExportService;
use App\Services\Tabulation\TabulationFinalizationService;
use App\Services\Tabulation\TabulationReadinessService;
use App\Services\Tabulation\TabulationReviewSummaryService;
use App\Services\Tabulation\TabulationRollbackService;
use App\Services\Tabulation\TabulationRuleConfig;
use App\Services\Tabulation\TabulationRunService;
use App\Services\Tabulation\TabulationSourceDerivedVerificationService;
use App\Services\Tabulation\TabulationStaleService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

final class TabulationController extends Controller
{
    public function index(
        TabulationReadinessService $readiness,
        TabulationRuleConfig $rules,
        TabulationStaleService $stale,
    ): View {
        $this->authorize('viewAny', TabulationResult::class);
        $stale->synchronize();

        $state = TabulationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        $audits = TabulationProcessingAudit::query()->latest('id')->limit(10)->get();
        $auditActors = User::query()
            ->whereIn('id', $audits->pluck('actor_id')->filter()->unique()->values())
            ->get(['id', 'name', 'email'])
            ->keyBy('id');

        return view('tabulation.index', [
            'state' => $state,
            'readiness' => $readiness->inspect(),
            'rules' => $rules->snapshot(),
            'latestRun' => TabulationProcessingRun::query()->latest('id')->first(),
            'latestFinalization' => TabulationFinalizationRun::query()->latest('id')->first(),
            'finalizationHistory' => TabulationFinalizationRun::query()->latest('id')->limit(10)->get(),
            'audits' => $audits,
            'auditActors' => $auditActors,
        ]);
    }

    public function start(Request $request, TabulationRunService $service, ExaminationContext $context): RedirectResponse
    {
        $this->authorize('process', TabulationResult::class);
        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination selected.');

        $run = $service->create($request->user());
        ProcessTabulation::dispatch($examId, $run->id);

        return redirect()->route('tabulation.run.show', $run)->with('success', 'Tabulation generation queued.');
    }

    public function runShow(TabulationProcessingRun $run): View
    {
        $this->authorize('viewAny', TabulationResult::class);

        return view('tabulation.run', compact('run'));
    }

    public function runStatus(TabulationProcessingRun $run): JsonResponse
    {
        $this->authorize('viewAny', TabulationResult::class);
        $run->refresh();

        return response()->json([
            'status' => $run->status,
            'total_rows' => (int) $run->total_rows,
            'processed_rows' => (int) $run->processed_rows,
            'valid_rows' => (int) $run->valid_rows,
            'warning_rows' => (int) $run->warning_rows,
            'error_rows' => (int) $run->error_rows,
            'progress_percent' => (float) $run->progress_percent,
            'current_step' => $run->current_step,
            'failure_message' => $run->failure_message,
            'finished' => ! in_array($run->status, ['queued', 'running'], true),
        ]);
    }

    public function results(
        Request $request,
        TabulationStaleService $stale,
        TabulationReviewSummaryService $summaryService,
    ): View {
        $this->authorize('viewAny', TabulationResult::class);
        $stale->synchronize();

        $state = TabulationProcessingState::query()->first();
        $runId = $request->integer('run') ?: $state?->latest_run_id;
        abort_if(! $runId, 404, 'No Tabulation run available.');

        $run = TabulationProcessingRun::query()->findOrFail($runId);
        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));
        $track = strtoupper(trim((string) $request->query('track', '')));
        $merit = trim((string) $request->query('merit', ''));
        $warning = trim((string) $request->query('warning', ''));

        $q = TabulationResult::query()
            ->leftJoin('registrations as registration_lookup', 'registration_lookup.id', '=', 'tabulation_results.registration_id')
            ->where('tabulation_results.processing_run_id', $runId)
            ->select('tabulation_results.*', 'registration_lookup.name as candidate_name');

        if ($search !== '') {
            $q->where(function ($query) use ($search): void {
                $query->where('tabulation_results.reg', 'like', "%{$search}%")
                    ->orWhere('tabulation_results.user_id', 'like', "%{$search}%")
                    ->orWhere('registration_lookup.name', 'like', "%{$search}%");
            });
        }

        if (in_array($status, ['valid', 'warning', 'error'], true)) {
            $q->where('tabulation_results.validation_status', $status);
        }

        if (in_array($track, ['GG', 'GN', 'TT', 'T', 'GT'], true)) {
            $q->where('tabulation_results.written_qualified_track', $track);
        }

        match ($merit) {
            'general' => $q->where('tabulation_results.general_merit_eligible', true)
                ->where('tabulation_results.technical_merit_eligible', false),
            'technical' => $q->where('tabulation_results.general_merit_eligible', false)
                ->where('tabulation_results.technical_merit_eligible', true),
            'both' => $q->where('tabulation_results.general_merit_eligible', true)
                ->where('tabulation_results.technical_merit_eligible', true),
            'none' => $q->where('tabulation_results.general_merit_eligible', false)
                ->where('tabulation_results.technical_merit_eligible', false),
            default => null,
        };

        match ($warning) {
            'general_high' => $q->whereJsonContains('tabulation_results.review_warnings', 'GENERAL_GRAND_TOTAL_HIGH_REVIEW'),
            'technical_high' => $q->whereJsonContains('tabulation_results.review_warnings', 'TECHNICAL_GRAND_TOTAL_HIGH_REVIEW'),
            default => null,
        };

        $rows = $q
            ->orderByRaw("CASE tabulation_results.validation_status WHEN 'error' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
            ->orderBy('tabulation_results.reg')
            ->paginate(100)
            ->withQueryString();

        return view('tabulation.results', [
            'rows' => $rows,
            'run' => $run,
            'search' => $search,
            'status' => $status,
            'track' => $track,
            'merit' => $merit,
            'warning' => $warning,
            'state' => $state,
            'reviewSummary' => $summaryService->forRun($run),
        ]);
    }

    public function show(
        TabulationResult $result,
        TabulationSourceDerivedVerificationService $verificationService,
    ): View {
        $this->authorize('view', $result);
        $state = TabulationProcessingState::query()->first();
        abort_unless(
            $state?->status === 'finalized'
            && $state?->latest_run_id === $result->processing_run_id
            && ! $state?->is_stale,
            409,
            'Individual Finalized View is available only for the current finalized Tabulation run.'
        );

        $registration = Registration::query()->findOrFail($result->registration_id);
        $preliminary = $result->preliminary_result_id ? PreliminaryResult::query()->find($result->preliminary_result_id) : null;
        $written = WrittenResult::query()->findOrFail($result->written_result_id);
        $viva = VivaResult::query()->findOrFail($result->viva_result_id);

        return view('tabulation.show', [
            'result' => $result,
            'registration' => $registration,
            'preliminary' => $preliminary,
            'written' => $written,
            'viva' => $viva,
            'verificationRows' => $verificationService->build($result, $preliminary, $written, $viva),
        ]);
    }

    public function rollback(
        Request $request,
        TabulationFinalizationRun $finalization,
        TabulationRollbackService $service,
    ): RedirectResponse {
        $this->authorize('process', TabulationResult::class);
        $validated = $request->validate([
            'confirmation' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:4000'],
        ]);

        $service->rollback(
            $finalization,
            $request->user(),
            $validated['confirmation'],
            $validated['reason'] ?? null,
        );

        return redirect()->route('tabulation.results', ['run' => $finalization->processing_run_id])
            ->with('success', 'Compatible historical Tabulation version restored.');
    }

    public function finalize(Request $request, TabulationFinalizationService $service): RedirectResponse
    {
        $this->authorize('process', TabulationResult::class);
        $validated = $request->validate([
            'confirmation' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $service->finalize(
            $request->user(),
            $validated['confirmation'],
            $validated['notes'] ?? null,
        );

        return redirect()->route('tabulation.results')->with('success', 'Tabulation finalized successfully.');
    }

    public function pdf(TabulationResult $result, TabulationIndividualPdfReport $report): Response
    {
        $this->authorize('view', $result);
        $state = TabulationProcessingState::query()->first();
        abort_unless(
            $state?->status === 'finalized'
            && $state?->latest_run_id === $result->processing_run_id
            && ! $state?->is_stale,
            409
        );

        $pdf = $report->generate($result);

        return response($pdf['content'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$pdf['filename'].'"',
        ]);
    }

    public function export(AdministrativeXlsxExportService $exporter): BinaryFileResponse
    {
        $this->authorize('viewAny', TabulationResult::class);
        $state = TabulationProcessingState::query()->first();
        abort_unless(
            $state?->status === 'finalized' && ! $state?->is_stale && $state?->latest_run_id,
            409,
            'Finalize the current Tabulation before export.'
        );

        $run = TabulationProcessingRun::query()->findOrFail($state->latest_run_id);
        $dir = storage_path('app/private/tabulation');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/tabulation-final-v'.$run->processing_version.'-'.now()->format('Ymd-His').'.xlsx';

        $headers = [
            'User', 'Reg', 'Name', 'Qualified Track',
            'Preliminary Mark', 'Preliminary Result',
            'Source General Written', 'Source General P/F',
            'Source Technical Written', 'Source Technical P/F',
            'Source Viva Mark', 'Source Viva Result',
            'Tab General Written', 'Tab Technical Written', 'Tab Viva',
            'General Grand Total', 'Technical Grand Total',
            'General P/F', 'Technical P/F',
            'General Merit Eligible', 'Technical Merit Eligible',
            'Validation', 'Validation Errors', 'Warnings',
            'Processing Version', 'Processed At',
        ];

        $rows = (function () use ($run) {
            $query = DB::connection('exam')
                ->table('tabulation_results as t')
                ->join('registrations as r', 'r.id', '=', 't.registration_id')
                ->leftJoin('preliminary_results as p', 'p.id', '=', 't.preliminary_result_id')
                ->join('written_results as w', 'w.id', '=', 't.written_result_id')
                ->join('viva_results as v', 'v.id', '=', 't.viva_result_id')
                ->where('t.processing_run_id', $run->id)
                ->orderBy('t.id')
                ->select([
                    't.*', 'r.name',
                    'p.result_status as source_preliminary_result',
                    'w.general_counted_total as source_general_written',
                    'w.general_result_status as source_general_pf',
                    'w.technical_counted_total as source_technical_written',
                    'w.technical_result_status as source_technical_pf',
                    'v.mark as source_viva_mark',
                    'v.viva_result_status as source_viva_result',
                ]);

            foreach ($query->cursor() as $row) {
                $warnings = json_decode($row->review_warnings ?: '[]', true) ?: [];
                $errors = json_decode($row->validation_errors ?: '[]', true) ?: [];

                yield [
                    $row->user_id,
                    $row->reg,
                    $row->name,
                    $row->written_qualified_track,
                    $row->preliminary_mark,
                    $row->source_preliminary_result,
                    $row->source_general_written,
                    strtoupper((string) $row->source_general_pf),
                    $row->source_technical_written,
                    strtoupper((string) $row->source_technical_pf),
                    $row->source_viva_mark,
                    strtoupper((string) $row->source_viva_result),
                    $row->general_written_total,
                    $row->technical_written_total,
                    $row->viva_mark,
                    TabulationResult::grandTotalDisplayFor($row->written_qualified_track, 'general', $row->general_grand_total),
                    TabulationResult::grandTotalDisplayFor($row->written_qualified_track, 'technical', $row->technical_grand_total),
                    $row->general_pf,
                    $row->technical_pf,
                    (bool) $row->general_merit_eligible,
                    (bool) $row->technical_merit_eligible,
                    $row->validation_status,
                    implode(', ', $errors),
                    implode(', ', $warnings),
                    $row->processing_version,
                    $row->processed_at,
                ];
            }
        })();

        $exporter->create(
            $path,
            [
                'Processing Version' => $run->processing_version,
                'Total Rows' => $run->total_rows,
                'Warnings' => $run->warning_rows,
                'Errors' => $run->error_rows,
                'General Merit Eligible' => $run->general_merit_eligible_count,
                'Technical Merit Eligible' => $run->technical_merit_eligible_count,
                'Finalized At' => $state->finalized_at?->format('Y-m-d H:i:s'),
            ],
            $headers,
            $rows,
        );

        return response()->download($path, basename($path))->deleteFileAfterSend();
    }
}
