<?php

namespace App\Http\Controllers;

use App\Models\ChoiceSource;
use App\Models\ChoiceValidationImportBatch;
use App\Models\ChoiceValidationProcessingAudit;
use App\Models\ChoiceValidationProcessingState;
use App\Models\ImportCorrectionEntry;
use App\Jobs\ProcessChoiceSourceValidation;
use App\Jobs\ProcessChoiceValidation;
use App\Models\ChoiceValidationRun;
use App\Models\ChoiceValidationResult;
use App\Models\ChoiceValidationManualCorrection;
use App\Models\WrittenResult;
use App\Enums\ChoiceValidationReason;
use App\Services\Circular\CircularFinalizedDatasetService;
use App\Services\ChoiceValidation\ChoiceColumnResolver;
use App\Services\ChoiceValidation\ChoiceInvalidRowCorrectionService;
use App\Services\ChoiceValidation\ChoiceSourceApprovalService;
use App\Services\ChoiceValidation\ChoiceSourceImportService;
use App\Services\ChoiceValidation\ChoiceTemplateService;
use App\Services\ChoiceValidation\ChoiceManualCorrectionService;
use App\Services\ChoiceValidation\ChoiceCandidateRevalidationService;
use App\Services\ChoiceValidation\ChoiceEffectiveSourceResolver;
use App\Services\ChoiceValidation\ChoiceValidationReadinessService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Models\ChoiceValidationFinalizationRun;
use App\Services\ChoiceValidation\ChoiceValidationFinalizationService;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use App\Reports\Pdf\ChoiceValidationFinalSummaryPdfReport;
use App\Reports\Excel\ChoiceValidationFinalSummaryExcelReport;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ChoiceValidationController extends Controller
{
    public function index(ChoiceColumnResolver $columns, ChoiceValidationFinalizationService $finalization, ChoiceValidationReadinessService $readiness): View
    {
        $this->authorize('viewAny', ChoiceSource::class);
        $state = ChoiceValidationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        $latestBatch = ChoiceValidationImportBatch::query()->latest('id')->first();
        $latestValidationRun = ChoiceValidationRun::query()->latest('id')->first();
        $finalizationReadiness = $finalization->readiness();
        $latestFinalizationRun = ChoiceValidationFinalizationRun::query()->latest('id')->first();
        $validationReadiness = $readiness->summary();

        return view('choice-validation.index', [
            'state' => $state,
            'maximumAllowedChoices' => $columns->maximumAllowedChoices(),
            'latestBatch' => $latestBatch,
            'batches' => ChoiceValidationImportBatch::query()->latest('id')->paginate(15),
            'sourceCount' => $state->approved_source_version
                ? ChoiceSource::query()->where('source_version', $state->approved_source_version)->count()
                : 0,
            'pendingCorrectionRows' => $latestBatch ? (int) $latestBatch->invalid_rows : 0,
            'sourceComplete' => $latestBatch
                ? ((int) $latestBatch->approved_rows > 0 && (int) $latestBatch->invalid_rows === 0 && $latestBatch->status === 'approved')
                : false,
            'audits' => ChoiceValidationProcessingAudit::query()->latest('id')->limit(10)->get(),
            'latestValidationRun' => $latestValidationRun,
            'finalizationReadiness' => $finalizationReadiness,
            'latestFinalizationRun' => $latestFinalizationRun,
            'validationReadiness' => $validationReadiness,
            'validationCounts' => [
                'valid' => ChoiceValidationResult::query()->where('validation_version', (int) $state->current_validation_version)->where('status', 'valid')->count(),
                'not_applicable' => ChoiceValidationResult::query()->where('validation_version', (int) $state->current_validation_version)->where('status', 'like', 'not_applicable%')->count(),
                'zero_valid' => ChoiceValidationResult::query()->where('validation_version', (int) $state->current_validation_version)->where('status', 'no_valid_choices')->count(),
            ],
        ]);
    }

    public function template(ChoiceTemplateService $service): BinaryFileResponse
    {
        $this->authorize('viewAny', ChoiceSource::class);
        $dir=storage_path('app/private/choice-validation');File::ensureDirectoryExists($dir);$path=$dir.'/choice-source-template.xlsx';$service->create($path);
        return response()->download($path,'choice-source-template.xlsx')->deleteFileAfterSend();
    }

    public function upload(Request $request, ChoiceSourceImportService $service, ExaminationContext $context): RedirectResponse
    {
        $this->authorize('process', ChoiceSource::class);
        $validated=$request->validate(['file'=>['required','file','mimes:xlsx,csv','max:102400']]);
        $examId=$context->currentId();abort_if($examId===null,409,'No examination is selected.');
        $batch=$service->enqueue($validated['file'],(int)$request->user()->id,(int)$examId);
        return redirect()->route('choice-validation.import.show',$batch)->with('success','Choice source file queued for staging.');
    }

    public function show(Request $request, ChoiceValidationImportBatch $batch): View
    {
        $this->authorize('viewAny', ChoiceSource::class);
        $validation=trim((string)$request->query('validation','all'));$search=trim((string)$request->query('search',''));
        $rows=$batch->stagingRows()->when($validation!=='all'&&$validation!=='',fn($q)=>$q->where('validation_status',$validation))
            ->when($search!=='',fn($q)=>$q->where(fn($n)=>$n->where('reg',$search)->orWhere('user_id',$search)))
            ->orderByRaw("CASE validation_status WHEN 'invalid' THEN 0 WHEN 'valid' THEN 1 ELSE 2 END")->orderBy('source_row')->paginate(100)->withQueryString();
        $approvableRows = max(0, (int) $batch->valid_rows - (int) $batch->approved_rows);
        $corrections = ImportCorrectionEntry::query()
            ->where('module', 'choice_validation')
            ->where('batch_id', $batch->id)
            ->latest('id')
            ->limit(10)
            ->get();

        return view('choice-validation.import-show', compact(
            'batch', 'rows', 'validation', 'search', 'approvableRows', 'corrections'
        ));
    }

    public function importStatus(ChoiceValidationImportBatch $batch): JsonResponse
    {
        $this->authorize('viewAny', ChoiceSource::class);

        $batch->refresh();
        $finished = ! in_array($batch->status, [
            'queued', 'processing', 'validation_queued', 'validating',
        ], true);

        return response()->json([
            'status' => $batch->status,
            'total_rows' => (int) $batch->total_rows,
            'processed_rows' => (int) $batch->processed_rows,
            'valid_rows' => (int) $batch->valid_rows,
            'invalid_rows' => (int) $batch->invalid_rows,
            'progress_percent' => (float) $batch->progress_percent,
            'failure_message' => $batch->failure_message,
            'finished' => $finished,
        ]);
    }

    public function validateSource(ChoiceValidationImportBatch $batch, Request $request, ExaminationContext $context): RedirectResponse
    {
        $this->authorize('process', ChoiceSource::class);
        abort_unless($batch->status === 'staged' || $batch->status === 'validation_failed', 409, 'Only a staged Choice source batch can be validated.');

        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination is selected.');

        $batch->update([
            'status' => 'validation_queued',
            'processed_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
        ]);

        ProcessChoiceSourceValidation::dispatch((int) $examId, (int) $batch->id, (int) $request->user()->id);

        return redirect()->route('choice-validation.import.show', $batch)
            ->with('success', 'Choice source validation queued. This page will refresh while the job is running.');
    }

    public function approve(ChoiceValidationImportBatch $batch, ChoiceSourceApprovalService $service, Request $request): RedirectResponse
    {
        $this->authorize('process', ChoiceSource::class);
        $summary = $service->approve($batch, $request->user());

        $message = number_format($summary['newly_approved']).' valid Choice source row(s) approved/merged into source version '.$summary['source_version'].'.';
        if (! $summary['source_complete']) {
            $message .= ' '.number_format($summary['pending_invalid']).' invalid row(s) remain pending correction; they do not block the approved valid rows.';
        } else {
            $message .= ' Source dataset is complete.';
        }

        return redirect()->route('choice-validation.import.show', $batch)->with('success', $message);
    }

    public function invalidRows(
        ChoiceValidationImportBatch $batch,
        ChoiceInvalidRowCorrectionService $service,
    ): BinaryFileResponse {
        $this->authorize('viewAny', ChoiceSource::class);
        abort_if((int) $batch->invalid_rows < 1, 409, 'This Choice source batch has no invalid rows to correct.');

        $directory = storage_path('app/private/import-corrections');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/choice-source-batch-'.$batch->id.'-invalid-rows.xlsx';
        $count = $service->createWorkbook($batch, $path);
        abort_if($count < 1, 409, 'This Choice source batch has no invalid rows to correct.');

        return response()->download($path, basename($path))->deleteFileAfterSend();
    }

    public function correctInvalidRows(
        ChoiceValidationImportBatch $batch,
        Request $request,
        ChoiceInvalidRowCorrectionService $service,
        ExaminationContext $context,
    ): RedirectResponse {
        $this->authorize('process', ChoiceSource::class);
        $validated = $request->validate([
            'correction_file' => ['required', 'file', 'mimes:xlsx,csv', 'max:102400'],
        ]);

        $summary = $service->apply($batch, $validated['correction_file'], $request->user());
        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination is selected.');

        $batch->update([
            'status' => 'validation_queued',
            'processed_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'validated_at' => null,
            'finished_at' => null,
        ]);

        ProcessChoiceSourceValidation::dispatch((int) $examId, (int) $batch->id, (int) $request->user()->id);

        return redirect()->route('choice-validation.import.show', $batch)->with(
            'success',
            number_format($summary['corrected_rows']).' invalid Choice source row(s) replaced from the correction workbook. Revalidation is running now.'
        );
    }

    public function processChoices(Request $request, ExaminationContext $context, CircularFinalizedDatasetService $circular, ChoiceValidationReadinessService $readiness): RedirectResponse
    {
        $this->authorize('process', ChoiceSource::class);
        ChoiceValidationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);

        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination is selected.');
        $readiness->assertReady();
        $circularVersion = $circular->finalizedVersion();

        $run = DB::connection('exam')->transaction(function () use ($request, $circularVersion): ChoiceValidationRun {
            // Serialize version allocation so retry/double-click cannot reserve the same
            // source_version + validation_version pair.
            $state = ChoiceValidationProcessingState::query()
                ->whereKey(1)
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($state->approved_source_version, 409, 'Approve a Choice source dataset first.');
            abort_if(
                ChoiceValidationRun::query()->whereIn('status', ['queued', 'running'])->exists(),
                409,
                'A Choice Validation run is already active.'
            );

            $sourceVersion = (int) $state->approved_source_version;
            $existingRunVersion = (int) (ChoiceValidationRun::query()
                ->where('source_version', $sourceVersion)
                ->max('validation_version') ?? 0);

            // A failed/aborted run may already have reserved a validation version while
            // current_validation_version still points to the last completed version.
            // Never reuse a previously allocated version.
            $version = max((int) $state->current_validation_version, $existingRunVersion) + 1;

            $total = ChoiceSource::query()->where('source_version', $sourceVersion)->count();
            abort_if($total < 1, 409, 'Approved Choice source dataset is empty.');

            $run = ChoiceValidationRun::query()->create([
                'source_version' => $sourceVersion,
                'validation_version' => $version,
                'circular_version' => $circularVersion,
                'status' => 'queued',
                'total_candidates' => $total,
                'started_by' => $request->user()->id,
            ]);

            $state->update([
                'status' => 'validation_queued',
                'latest_validation_run_id' => $run->id,
                'is_stale' => false,
                'stale_reason' => null,
                'finalized_validation_version' => null,
                'latest_finalization_run_id' => null,
                'finalized_at' => null,
            ]);

            ChoiceValidationProcessingAudit::query()->create([
                'action' => 'CHOICE_VALIDATION_QUEUED',
                'actor_id' => $request->user()->id,
                'actor_name' => $request->user()->name ?? null,
                'reason' => 'Queued Choice Validation against finalized Circular.',
                'summary' => [
                    'run_id' => $run->id,
                    'source_version' => $run->source_version,
                    'validation_version' => $version,
                    'circular_version' => $circularVersion,
                ],
                'created_at' => now(),
            ]);

            return $run;
        });

        ProcessChoiceValidation::dispatch((int) $examId, (int) $run->id);

        return redirect()
            ->route('choice-validation.results', ['run' => $run->id])
            ->with('success', 'Choice Validation queued.');
    }

    public function results(Request $request, ?ChoiceValidationRun $run=null): View
    {
        $this->authorize('viewAny',ChoiceSource::class);
        $run=$run?->exists?$run:ChoiceValidationRun::query()->latest('id')->first();
        abort_if(!$run,404,'No Choice Validation run found.');

        $status=trim((string)$request->query('status','all'));
        $reason=trim((string)$request->query('reason',''));
        $search=trim((string)$request->query('search',''));

        $base = ChoiceValidationResult::query()->where('validation_version',$run->validation_version);
        $notApplicableBreakdown = (clone $base)
            ->where('status','like','not_applicable%')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate','status');

        $statusOptions = (clone $base)
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values();

        $rows=(clone $base)
            ->with(['items','registration'])
            ->when($status!=='all'&&$status!=='',fn($q)=>$q->where('status',$status))
            ->when($reason!=='',fn($q)=>$q->whereHas('items',fn($i)=>$i->where('reason_code',$reason)))
            ->when($search!=='',fn($q)=>$q->where(fn($n)=>$n->where('reg',$search)->orWhere('user_id',$search)))
            ->orderBy('reg')
            ->paginate(100)
            ->withQueryString();

        $reasonOptions = array_map(static fn (ChoiceValidationReason $case): string => $case->value, ChoiceValidationReason::cases());

        return view('choice-validation.results',compact(
            'run','rows','status','reason','search','reasonOptions','statusOptions','notApplicableBreakdown'
        ));
    }


    public function validationProgress(ChoiceValidationRun $run): JsonResponse
    {
        $this->authorize('viewAny', ChoiceSource::class);

        $run->refresh();

        return response()->json([
            'status' => $run->status,
            'total' => (int) $run->total_candidates,
            'processed' => (int) $run->processed_candidates,
            'percent' => (float) $run->progress_percent,
            'valid' => (int) $run->valid_candidates,
            'not_applicable' => (int) $run->not_applicable_candidates,
            'no_valid_choice' => (int) $run->zero_valid_choice_candidates,
            'kept' => (int) $run->kept_choices,
            'removed' => (int) $run->removed_choices,
            'expanded' => (int) $run->expanded_choices,
            'failure_message' => $run->failure_message,
            'terminal' => in_array($run->status, ['completed', 'failed'], true),
        ]);
    }

    public function resultDetail(ChoiceValidationResult $result, ChoiceEffectiveSourceResolver $effectiveSource, ChoiceColumnResolver $columns): View
    {
        $this->authorize('viewAny', ChoiceSource::class);

        $result->load([
            'items' => fn ($query) => $query->orderBy('source_position')->orderBy('output_position'),
            'source' => fn ($query) => $query->with(['items' => fn ($items) => $items->orderBy('position')]),
            'registration',
        ]);

        $writtenResult = WrittenResult::query()
            ->where('registration_id', $result->registration_id)
            ->whereNotNull('finalized_at')
            ->latest('finalized_at')
            ->first()
            ?? WrittenResult::query()
                ->where('registration_id', $result->registration_id)
                ->latest('id')
                ->first();

        $corrections = ChoiceValidationManualCorrection::query()
            ->where('choice_source_id', $result->choice_source_id)
            ->latest('id')
            ->get();

        $effectiveSnapshot = $effectiveSource->snapshot($result->source);
        $choiceColumns = $columns->choiceColumns();

        $originalChoices = $result->source?->items
            ?->filter(fn ($item) => filled($item->choice_code ?? $item->raw_value))
            ->map(fn ($item) => [
                'position' => (int) $item->position,
                'column' => (string) $item->source_column,
                'code' => (string) ($item->choice_code ?? $item->raw_value),
            ])
            ->values()
            ->all() ?? [];

        $effectiveChoices = [];
        foreach ($choiceColumns as $index => $column) {
            $value = trim((string) ($effectiveSnapshot[$column] ?? ''));
            if ($value === '') {
                continue;
            }

            $effectiveChoices[] = [
                'position' => $index + 1,
                'column' => $column,
                'code' => $value,
            ];
        }

        $resolutionByPosition = $result->items
            ->groupBy(fn ($item) => (int) $item->source_position)
            ->map(function ($items): array {
                $results = $items->pluck('result')->filter()->map(fn ($value) => strtolower((string) $value));

                if ($results->contains('expanded')) {
                    $visual = 'expanded';
                } elseif ($results->contains('removed')) {
                    $visual = 'removed';
                } else {
                    $visual = 'retained';
                }

                return [
                    'visual' => $visual,
                    'reason_codes' => $items->pluck('reason_code')->filter()->unique()->values()->all(),
                    'outputs' => $items->pluck('output_code')->filter()->unique()->values()->all(),
                ];
            })
            ->all();

        $expandedOutputCodes = $result->items
            ->filter(fn ($item) => strtolower((string) $item->result) === 'expanded' && filled($item->output_code))
            ->pluck('output_code')
            ->map(fn ($code) => (string) $code)
            ->unique()
            ->values()
            ->all();

        $originalByPosition = collect($originalChoices)->keyBy('position');
        $effectiveByPosition = collect($effectiveChoices)->keyBy('position');
        $hasEffectiveCorrection = collect($choiceColumns)->contains(function ($column, $index) use ($originalByPosition, $effectiveSnapshot): bool {
            $position = $index + 1;
            $original = trim((string) ($originalByPosition->get($position)['code'] ?? ''));
            $effective = trim((string) ($effectiveSnapshot[$column] ?? ''));

            return $original !== $effective;
        });

        return view('choice-validation.result-detail', compact(
            'result',
            'writtenResult',
            'corrections',
            'effectiveSnapshot',
            'choiceColumns',
            'originalChoices',
            'effectiveChoices',
            'resolutionByPosition',
            'expandedOutputCodes',
            'hasEffectiveCorrection',
        ));
    }

    public function correctResult(
        ChoiceValidationResult $result,
        Request $request,
        ChoiceManualCorrectionService $corrections,
        ChoiceCandidateRevalidationService $revalidation,
        ChoiceColumnResolver $columns,
    ): RedirectResponse {
        $this->authorize('process', ChoiceSource::class);
        $rules = ['reason' => ['required', 'string', 'max:2000']];
        foreach ($columns->choiceColumns() as $column) {
            $rules[$column] = ['nullable', 'string', 'max:40'];
        }

        $input = $request->validate($rules);
        $summary = $corrections->correct($result, $input, $request->user(), $request);
        if (! $summary['changed']) {
            return redirect()->route('choice-validation.result.detail', $result)
                ->with('success', 'No actual Choice change detected. No audit event was created.');
        }

        $revalidation->revalidate($result, $request->user(), $summary['correction']);

        return redirect()->route('choice-validation.result.detail', $result)
            ->with('success', 'Choice correction saved, audited and candidate revalidated successfully.');
    }

    public function finalization(
        ChoiceValidationFinalizationService $service,
    ): View {
        $this->authorize('viewAny', ChoiceSource::class);

        return view('choice-validation.finalization', [
            'readiness' => $service->readiness(),
            'history' => ChoiceValidationFinalizationRun::query()
                ->latest('id')
                ->limit(20)
                ->get(),
        ]);
    }

    public function finalizeValidation(
        Request $request,
        ChoiceValidationFinalizationService $service,
    ): RedirectResponse {
        $this->authorize('process', ChoiceSource::class);

        $validated = $request->validate([
            'finalization_note' => ['required', 'string', 'min:3', 'max:5000'],
        ]);

        try {
            $run = $service->finalize(
                $request->user(),
                $validated['finalization_note'],
            );
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()
            ->route('choice-validation.final-report.index')
            ->with(
                'success',
                "Choice Validation version {$run->validation_version} finalized successfully."
            );
    }

    public function finalReport(
        ChoiceValidationFinalizedDatasetService $dataset,
        Request $request,
    ): View {
        $this->authorize('viewAny', ChoiceSource::class);

        $summary = $dataset->summary();
        $version = $dataset->finalizedVersion();

        $status = trim((string) $request->query('status', 'all'));
        $search = trim((string) $request->query('search', ''));

        $query = ChoiceValidationResult::query()
            ->with(['registration', 'source.items'])
            ->where('validation_version', $version)
            ->when(
                $status !== 'all' && $status !== '',
                fn ($builder) => $builder->where('status', $status)
            )
            ->when(
                $search !== '',
                fn ($builder) => $builder->where(
                    fn ($nested) => $nested
                        ->where('reg', $search)
                        ->orWhere('user_id', $search)
                )
            )
            ->orderBy('reg');

        $statusOptions = ChoiceValidationResult::query()
            ->where('validation_version', $version)
            ->select('status')
            ->distinct()
            ->orderBy('status')
            ->pluck('status')
            ->filter()
            ->values();

        $notApplicableBreakdown = ChoiceValidationResult::query()
            ->where('validation_version', $version)
            ->where('status', 'like', 'not_applicable%')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $rows = $query->paginate(100)->withQueryString();

        return view('choice-validation.final-report', compact(
            'summary',
            'rows',
            'status',
            'search',
            'statusOptions',
            'notApplicableBreakdown',
        ));
    }

    public function finalReportPdf(
        ChoiceValidationFinalSummaryPdfReport $report,
    ): StreamedResponse {
        $this->authorize('viewAny', ChoiceSource::class);

        try {
            $file = $report->generate();
        } catch (ValidationException $exception) {
            abort(
                422,
                collect($exception->errors())->flatten()->first()
                    ?? 'Finalized Choice Validation is required.'
            );
        }

        return response()->streamDownload(
            static function () use ($file): void {
                echo $file['content'];
            },
            $file['filename'],
            ['Content-Type' => 'application/pdf']
        );
    }

    public function finalReportExcel(
        ChoiceValidationFinalSummaryExcelReport $report,
    ): StreamedResponse {
        $this->authorize('viewAny', ChoiceSource::class);

        try {
            $file = $report->generate();
        } catch (ValidationException $exception) {
            abort(
                422,
                collect($exception->errors())->flatten()->first()
                    ?? 'Finalized Choice Validation is required.'
            );
        }

        return response()->streamDownload(
            static function () use ($file): void {
                echo $file['content'];
            },
            $file['filename'],
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

}
