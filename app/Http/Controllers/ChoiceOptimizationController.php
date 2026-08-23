<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessChoiceOptimizationHistoricalPull;
use App\Jobs\ProcessChoiceOptimizationOmrApproval;
use App\Jobs\ProcessChoiceOptimizationOmrValidation;
use App\Models\ChoiceOptimizationEffectiveChoice;
use App\Models\ChoiceOptimizationHistoricalSource;
use App\Models\ChoiceOptimizationProcessingAudit;
use App\Models\ChoiceOptimizationOmrBatch;
use App\Models\ChoiceOptimizationOmrStaging;
use App\Models\ChoiceValidationResult;
use App\Models\PreviousBcsRepository;
use App\Models\WrittenProcessingState;
use App\Models\User;
use App\Services\ChoiceOptimization\ChoiceOptimizationOmrDecisionResolutionService;
use App\Services\ChoiceOptimization\ChoiceOptimizationOmrImportService;
use App\Services\ChoiceOptimization\ChoiceOptimizationOmrResolutionService;
use App\Services\ChoiceOptimization\ChoiceOptimizationOmrTemplateService;
use App\Services\ChoiceOptimization\ChoiceOptimizationSettingsService;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ChoiceOptimizationController extends Controller
{
    public function index(ChoiceOptimizationSettingsService $settings): View
    {
        $setting = $settings->setting();
        $latestOmrBatch = $setting->optimization_enabled
            ? ChoiceOptimizationOmrBatch::query()->latest('id')->first()
            : null;

        $historicalRepositories = collect();
        $historicalSourceMap = collect();

        if ($setting->optimization_enabled) {
            $historicalRepositories = PreviousBcsRepository::query()
                ->with('currentEffectiveDataset')
                ->whereNotNull('current_effective_dataset_id')
                ->orderBy('bcs_number')
                ->get()
                ->filter(fn (PreviousBcsRepository $repository): bool =>
                    $repository->currentEffectiveDataset?->status === 'effective'
                    && filled($repository->currentEffectiveDataset?->dataset_hash)
                )
                ->values();

            $historicalSourceMap = ChoiceOptimizationHistoricalSource::query()
                ->get()
                ->keyBy('previous_bcs_number');
        }

        return view('choice-optimization.index', [
            'setting' => $setting,
            'state' => $settings->state(),
            'latestOmrBatch' => $latestOmrBatch,
            'omrBatches' => $setting->optimization_enabled
                ? ChoiceOptimizationOmrBatch::query()->latest('id')->limit(10)->get()
                : collect(),
            'historicalRepositories' => $historicalRepositories,
            'historicalSourceMap' => $historicalSourceMap,
        ]);
    }

    public function updateSetting(Request $request, ChoiceOptimizationSettingsService $settings): RedirectResponse
    {
        $validated = $request->validate([
            'optimization_enabled' => ['required', 'in:0,1'],
        ]);

        $enabled = $validated['optimization_enabled'] === '1';
        $settings->updateEnabled($enabled, $request->user()?->getAuthIdentifier());

        return redirect()->route('choice-optimization.index')->with(
            'success',
            $enabled
                ? 'Choice Optimization enabled. Allocation will require finalized optimized choices.'
                : 'Choice Optimization disabled. Allocation will use finalized Validated Choices directly.'
        );
    }

    public function omrTemplate(ChoiceOptimizationSettingsService $settings, ChoiceOptimizationOmrTemplateService $service): BinaryFileResponse
    {
        $this->assertOptimizationEnabled($settings);
        $dir = storage_path('app/private/choice-optimization');
        File::ensureDirectoryExists($dir);
        $path = $dir.'/viva-omr-choice-template.xlsx';
        $service->create($path);

        return response()->download($path, 'viva-omr-choice-template.xlsx')->deleteFileAfterSend();
    }

    public function uploadOmr(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationOmrImportService $service,
        ExaminationContext $context,
    ): RedirectResponse {
        $this->assertOptimizationEnabled($settings);
        $validated = $request->validate(['file' => ['required', 'file', 'mimes:xlsx,csv', 'max:524288']]);
        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination is selected.');

        $batch = $service->enqueue($validated['file'], (int) $request->user()->id, (int) $examId);

        return redirect()->route('choice-optimization.omr.show', $batch)
            ->with('success', 'Viva OMR choice file queued for raw staging. Progress is reported through JSON polling.');
    }

    public function showOmr(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationOmrBatch $batch,
        ChoiceValidationFinalizedDatasetService $finalizedChoices,
    ): View {
        $this->assertOptimizationEnabled($settings);
        $status = trim((string) $request->query('status', 'all'));
        $search = trim((string) $request->query('search', ''));

        $rows = $batch->stagingRows()
            ->when($status !== '' && $status !== 'all', function ($q) use ($status): void {
                if ($status === 'warning') {
                    $q->whereRaw('JSON_LENGTH(COALESCE(validation_warnings, JSON_ARRAY())) > 0');
                    return;
                }

                if ($status === 'operator_confirmed') {
                    $q->where(function ($confirmed): void {
                        $confirmed->whereNotNull('decision_resolved_at')
                            ->orWhereNotNull('resolved_at');
                    });
                    return;
                }

                $q->where('validation_status', $status);
            })
            ->when($search !== '', fn ($q) => $q->where(fn ($n) => $n
                ->where('raw_reg', $search)
                ->orWhere('effective_reg', $search)))
            ->orderByRaw("CASE validation_status WHEN 'conflict' THEN 0 WHEN 'decision_review' THEN 1 WHEN 'invalid' THEN 2 WHEN 'pending' THEN 3 ELSE 4 END")
            ->orderBy('source_row')
            ->paginate(100)
            ->withQueryString();

        $validatedChoiceMap = [];
        $registrationChoiceMap = [];
        $candidateContextMap = [];
        $state = $finalizedChoices->state();
        $version = (int) ($state->finalized_validation_version ?? 0);
        $registrationIds = collect($rows->items())->pluck('registration_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($version > 0 && $registrationIds !== []) {
            $comparisonRows = ChoiceValidationResult::query()
                ->with(['registration', 'source.items'])
                ->where('validation_version', $version)
                ->whereIn('registration_id', $registrationIds)
                ->get();

            foreach ($comparisonRows as $result) {
                $registrationId = (int) $result->registration_id;
                $validatedChoiceMap[$registrationId] = array_values((array) $result->validated_choice_codes);
                $registrationChoiceMap[$registrationId] = collect($result->source?->items ?? [])
                    ->sortBy('position')
                    ->map(fn ($item): string => trim((string) ($item->raw_value ?: $item->choice_code)))
                    ->filter(fn (string $value): bool => $value !== '')
                    ->values()
                    ->all();

                $category = $result->registration?->cadre_category;
                $candidateContextMap[$registrationId] = [
                    'name' => (string) ($result->registration?->name ?? ''),
                    'category_code' => is_object($category) && method_exists($category, 'code') ? $category->code() : (string) ($category ?? ''),
                ];
            }
        }

        $remainingOperatorReviews = $this->remainingOmrOperatorReviews((int) $batch->id);

        return view('choice-optimization.omr-show', compact(
            'batch', 'rows', 'status', 'search', 'validatedChoiceMap', 'registrationChoiceMap', 'candidateContextMap',
            'remainingOperatorReviews'
        ));
    }


    public function showOmrRow(
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationOmrBatch $batch,
        ChoiceOptimizationOmrStaging $row,
        ChoiceValidationFinalizedDatasetService $finalizedChoices,
    ): View {
        $this->assertOptimizationEnabled($settings);
        abort_unless((int) $row->batch_id === (int) $batch->id, 404);

        $state = $finalizedChoices->state();
        $version = (int) ($state->finalized_validation_version ?? 0);

        $choiceValidationResult = null;
        $registrationChoices = [];
        $validatedChoices = [];
        $candidate = null;

        if ($version > 0 && $row->registration_id) {
            $choiceValidationResult = ChoiceValidationResult::query()
                ->with(['registration', 'source.items'])
                ->where('validation_version', $version)
                ->where('registration_id', (int) $row->registration_id)
                ->first();

            if ($choiceValidationResult) {
                $candidate = $choiceValidationResult->registration;
                $validatedChoices = array_values((array) $choiceValidationResult->validated_choice_codes);
                $registrationChoices = collect($choiceValidationResult->source?->items ?? [])
                    ->sortBy('position')
                    ->map(fn ($item): string => trim((string) ($item->raw_value ?: $item->choice_code)))
                    ->filter(fn (string $value): bool => $value !== '')
                    ->values()
                    ->all();
            }
        }

        $effectiveChoice = ChoiceOptimizationEffectiveChoice::query()
            ->where('omr_staging_id', (int) $row->id)
            ->first();

        $actorIds = collect([$row->resolved_by, $row->decision_resolved_by])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $actors = $actorIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $actorIds)->get()->keyBy('id');

        return view('choice-optimization.omr-row-detail', compact(
            'batch',
            'row',
            'candidate',
            'registrationChoices',
            'validatedChoices',
            'choiceValidationResult',
            'effectiveChoice',
            'actors',
        ));
    }


    public function omrStatus(ChoiceOptimizationSettingsService $settings, ChoiceOptimizationOmrBatch $batch): JsonResponse
    {
        $this->assertOptimizationEnabled($settings);
        $batch->refresh();
        $running = in_array($batch->status, [
            'queued', 'processing', 'validation_queued', 'validating', 'approval_queued', 'approving',
        ], true);

        return response()->json([
            'status' => $batch->status,
            'total_rows' => (int) $batch->total_rows,
            'processed_rows' => (int) $batch->processed_rows,
            'valid_rows' => (int) $batch->valid_rows,
            'invalid_rows' => (int) $batch->invalid_rows,
            'conflict_rows' => (int) $batch->conflict_rows,
            'review_rows' => (int) $batch->review_rows,
            'approved_rows' => (int) $batch->approved_rows,
            'progress_percent' => (float) $batch->progress_percent,
            'failure_message' => $batch->failure_message,
            'running' => $running,
            'finished' => ! $running,
        ]);
    }

    public function validateOmr(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationOmrBatch $batch,
        ExaminationContext $context,
    ): RedirectResponse {
        $this->assertOptimizationEnabled($settings);
        abort_unless(in_array($batch->status, ['staged', 'needs_review', 'validation_failed'], true), 409, 'Only a staged/review OMR batch can be validated.');

        return $this->queueOmrValidation(
            request: $request,
            batch: $batch,
            context: $context,
            message: 'OMR identity, decision and override-choice validation queued.',
        );
    }

    public function revalidateOmr(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationOmrBatch $batch,
        ExaminationContext $context,
    ): RedirectResponse {
        $this->assertOptimizationEnabled($settings);
        abort_unless(
            in_array((string) $batch->status, ['validated', 'needs_review', 'validation_failed'], true),
            409,
            'Only a completed OMR validation can be re-validated.'
        );

        return $this->queueOmrValidation(
            request: $request,
            batch: $batch,
            context: $context,
            message: 'OMR re-validation queued. Previous derived validation output is no longer current.',
        );
    }

    public function resolveOmrRegistration(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationOmrStaging $row,
        ChoiceOptimizationOmrResolutionService $service,
    ): RedirectResponse|JsonResponse {
        $this->assertOptimizationEnabled($settings);
        $validated = $request->validate([
            'effective_reg' => ['required', 'string', 'max:32'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $service->correctRegistration($row, $validated['effective_reg'], $validated['reason'], $request->user()?->getAuthIdentifier());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Effective OMR registration corrected with audit trail.',
                'remaining_review_rows' => $this->remainingOmrOperatorReviews((int) $row->batch_id),
            ]);
        }

        return redirect()->route('choice-optimization.omr.show', $row->batch_id)
            ->with('success', 'Effective OMR registration corrected with audit trail. Re-run queued validation.');
    }

    public function resolveOmrDecision(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationOmrStaging $row,
        ChoiceOptimizationOmrDecisionResolutionService $service,
    ): RedirectResponse|JsonResponse {
        $this->assertOptimizationEnabled($settings);
        $validated = $request->validate([
            'resolution' => ['required', 'in:consider_no_as_yes_keep_options,keep_no_discard_options'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $service->resolve($row, $validated['resolution'], $validated['reason'], $request->user()?->getAuthIdentifier());

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'NO-with-options interpretation saved with audit trail.',
                'remaining_review_rows' => $this->remainingOmrOperatorReviews((int) $row->batch_id),
            ]);
        }

        return redirect()->route('choice-optimization.omr.show', $row->batch_id)
            ->with('success', 'NO-with-options interpretation saved with audit trail. Re-run queued validation before approval.');
    }

    public function approveOmr(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationOmrBatch $batch,
        ExaminationContext $context,
    ): RedirectResponse {
        $this->assertOptimizationEnabled($settings);
        abort_unless($batch->status === 'validated', 409, 'Only a fully validated OMR batch can be approved.');
        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination is selected.');

        $batch->update([
            'status' => 'approval_queued',
            'processed_rows' => 0,
            'approved_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
        ]);

        ProcessChoiceOptimizationOmrApproval::dispatch((int) $examId, (int) $batch->id, (int) $request->user()->id);

        return redirect()->route('choice-optimization.omr.show', $batch)
            ->with('success', 'OMR approval and effective-choice consolidation queued.');
    }


    public function pullHistorical(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ExaminationContext $context,
    ): RedirectResponse {
        $this->assertOptimizationEnabled($settings);

        $validated = $request->validate([
            'bcs_numbers' => ['required', 'array', 'min:1'],
            'bcs_numbers.*' => ['required', 'integer', 'min:1', 'max:999', 'distinct'],
        ]);

        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination is selected.');

        $writtenState = WrittenProcessingState::query()->first();
        abort_unless(
            $writtenState?->result_finalized_at && ! (bool) $writtenState->is_stale,
            409,
            'Finalize a current, non-stale Written result before pulling Previous BCS data.'
        );

        $bcsNumbers = collect($validated['bcs_numbers'])
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values();

        $repositories = PreviousBcsRepository::query()
            ->with('currentEffectiveDataset')
            ->whereIn('bcs_number', $bcsNumbers)
            ->get()
            ->keyBy('bcs_number');

        abort_unless(
            $repositories->count() === $bcsNumbers->count(),
            422,
            'One or more selected BCS repositories were not found.'
        );

        $queued = 0;
        $skippedRunning = 0;
        $repulls = 0;

        foreach ($bcsNumbers as $bcsNumber) {
            /** @var PreviousBcsRepository $repository */
            $repository = $repositories->get($bcsNumber);
            $dataset = $repository->currentEffectiveDataset;

            abort_unless(
                $dataset && $dataset->status === 'effective' && filled($dataset->dataset_hash),
                409,
                "BCS {$bcsNumber} has no effective Previous BCS repository dataset."
            );

            $existing = ChoiceOptimizationHistoricalSource::query()
                ->where('previous_bcs_number', $bcsNumber)
                ->first();

            if ($existing && in_array($existing->status, ['pull_queued', 'pulling'], true)) {
                $skippedRunning++;
                continue;
            }

            $fromStatus = $existing?->status ?? 'not_pulled';

            $source = ChoiceOptimizationHistoricalSource::query()->updateOrCreate(
                ['previous_bcs_number' => $bcsNumber],
                [
                    'repository_dataset_id' => (int) $dataset->id,
                    'repository_dataset_version' => (int) $dataset->version,
                    'repository_dataset_hash' => (string) $dataset->dataset_hash,
                    'status' => 'pull_queued',
                    'candidate_count' => 0,
                    'matched_count' => 0,
                    'review_count' => 0,
                    'no_match_count' => 0,
                    'matching_algorithm' => 'co4c1-core-v1',
                    'failure_message' => null,
                ],
            );

            ChoiceOptimizationProcessingAudit::query()->create([
                'event' => $existing ? 'HISTORICAL_REPULL_QUEUED' : 'HISTORICAL_PULL_QUEUED',
                'actor_id' => (int) $request->user()->getAuthIdentifier(),
                'from_status' => $fromStatus,
                'to_status' => 'pull_queued',
                'context' => [
                    'previous_bcs_number' => $bcsNumber,
                    'repository_dataset_id' => (int) $dataset->id,
                    'repository_dataset_version' => (int) $dataset->version,
                    'dataset_hash' => (string) $dataset->dataset_hash,
                ],
                'created_at' => now(),
            ]);

            ProcessChoiceOptimizationHistoricalPull::dispatch(
                (int) $examId,
                (int) $source->id,
                (int) $request->user()->getAuthIdentifier(),
            );

            $queued++;
            if ($existing) {
                $repulls++;
            }
        }

        $message = "{$queued} Historical BCS source(s) queued for Pull/Re-pull.";
        if ($repulls > 0) {
            $message .= " {$repulls} existing workspace snapshot(s) will be replaced.";
        }
        if ($skippedRunning > 0) {
            $message .= " {$skippedRunning} already-running source(s) were skipped.";
        }

        return redirect()
            ->route('choice-optimization.index')
            ->with('success', $message);
    }

    public function showHistorical(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationHistoricalSource $source,
    ): View {
        $this->assertOptimizationEnabled($settings);

        $status = trim((string) $request->query('status', 'all'));
        $search = trim((string) $request->query('search', ''));

        $matches = $source->matches()
            ->when($status !== 'all' && $status !== '', fn ($query) => $query->where('match_status', $status))
            ->when($search !== '', function ($query) use ($search): void {
                $like = '%'.$search.'%';
                $query->where(function ($nested) use ($like): void {
                    $nested->where('current_reg', 'like', $like)
                        ->orWhere('previous_reg', 'like', $like)
                        ->orWhere('previous_name', 'like', $like)
                        ->orWhere('previous_fname', 'like', $like)
                        ->orWhere('previous_mname', 'like', $like)
                        ->orWhere('previous_cadre', 'like', $like);
                });
            })
            ->orderByRaw("CASE match_status WHEN 'review' THEN 0 ELSE 1 END")
            ->orderBy('current_reg')
            ->paginate(100)
            ->withQueryString();

        $repository = PreviousBcsRepository::query()
            ->with('currentEffectiveDataset')
            ->where('bcs_number', (int) $source->previous_bcs_number)
            ->first();

        $updateAvailable = $repository?->currentEffectiveDataset
            && (int) $repository->currentEffectiveDataset->id !== (int) $source->repository_dataset_id;

        return view('choice-optimization.historical-show', compact(
            'source',
            'matches',
            'status',
            'search',
            'repository',
            'updateAvailable',
        ));
    }

    public function historicalStatus(
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationHistoricalSource $source,
    ): JsonResponse {
        $this->assertOptimizationEnabled($settings);
        $source->refresh();

        $running = in_array($source->status, ['pull_queued', 'pulling'], true);

        return response()->json([
            'status' => $source->status,
            'running' => $running,
            'candidate_count' => (int) $source->candidate_count,
            'matched_count' => (int) $source->matched_count,
            'review_count' => (int) $source->review_count,
            'no_match_count' => (int) $source->no_match_count,
            'failure_message' => $source->failure_message,
        ]);
    }

    private function queueOmrValidation(
        Request $request,
        ChoiceOptimizationOmrBatch $batch,
        ExaminationContext $context,
        string $message,
    ): RedirectResponse {
        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination is selected.');

        // Preserve raw OMR evidence and operator resolutions. Invalidate only
        // derived validation data so an older validation cannot be treated as current.
        $batch->stagingRows()->update([
            'choice_validation_status' => 'pending',
            'validated_omr_choice_codes' => null,
            'choice_validation_details' => null,
            'validation_status' => 'pending',
            'validation_errors' => null,
            'validation_warnings' => null,
        ]);

        $batch->update([
            'status' => 'validation_queued',
            'processed_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'conflict_rows' => 0,
            'review_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'validated_at' => null,
            'finished_at' => null,
        ]);

        ProcessChoiceOptimizationOmrValidation::dispatch(
            (int) $examId,
            (int) $batch->id,
            (int) $request->user()->id
        );

        return redirect()->route('choice-optimization.omr.show', $batch)
            ->with('success', $message);
    }



    private function remainingOmrOperatorReviews(int $batchId): int
    {
        $identityErrorCodes = [
            'INVALID_OMR_REGISTRATION',
            'WRITTEN_REGISTRATION_AMBIGUOUS',
            'DUPLICATE_OMR_REGISTRATION',
            'OMR_REGISTRATION_REQUIRED',
        ];

        return ChoiceOptimizationOmrStaging::query()
            ->where('batch_id', $batchId)
            ->whereIn('validation_status', ['decision_review', 'conflict', 'invalid'])
            ->get(['validation_status', 'validation_errors'])
            ->filter(function (ChoiceOptimizationOmrStaging $candidate) use ($identityErrorCodes): bool {
                if ((string) $candidate->validation_status === 'decision_review') {
                    return true;
                }

                return collect((array) $candidate->validation_errors)
                    ->contains(fn ($error): bool => in_array($error['code'] ?? '', $identityErrorCodes, true));
            })
            ->count();
    }

    private function assertOptimizationEnabled(ChoiceOptimizationSettingsService $settings): void
    {
        abort_unless((bool) $settings->setting()->optimization_enabled, 409, 'Choice Optimization is disabled for this examination.');
    }
}
