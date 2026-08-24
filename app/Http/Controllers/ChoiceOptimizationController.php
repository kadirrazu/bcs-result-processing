<?php

namespace App\Http\Controllers;

use App\Jobs\FinalizeChoiceOptimizationHistoricalChoice;
use App\Jobs\ProcessChoiceOptimizationHistoricalChoice;
use App\Jobs\ProcessChoiceOptimizationHistoricalPull;
use App\Jobs\ProcessChoiceOptimizationOmrApproval;
use App\Jobs\ProcessChoiceOptimizationOmrValidation;
use App\Models\ChoiceOptimizationEffectiveChoice;
use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\ChoiceOptimizationHistoricalMatch;
use App\Models\ChoiceOptimizationHistoricalSource;
use App\Models\ChoiceOptimizationProcessingAudit;
use App\Models\ChoiceOptimizationOmrBatch;
use App\Models\ChoiceOptimizationOmrStaging;
use App\Models\ChoiceValidationResult;
use App\Models\District;
use App\Models\PreviousBcsRepository;
use App\Models\WrittenProcessingState;
use App\Models\User;
use App\Services\ChoiceOptimization\ChoiceOptimizationOmrDecisionResolutionService;
use App\Services\ChoiceOptimization\ChoiceOptimizationOmrImportService;
use App\Services\ChoiceOptimization\ChoiceOptimizationOmrResolutionService;
use App\Services\ChoiceOptimization\ChoiceOptimizationOmrTemplateService;
use App\Services\ChoiceOptimization\ChoiceOptimizationHistoricalReviewService;
use App\Services\ChoiceOptimization\ChoiceOptimizationHistoricalStalenessService;
use App\Services\ChoiceOptimization\ChoiceOptimizationHistoricalChoiceFinalizationService;
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

        $historicalPendingReviewCount = 0;
        $historicalOptimizationRows = 0;

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

            $historicalPendingReviewCount = ChoiceOptimizationHistoricalMatch::query()
                ->where('match_status', 'review')
                ->where('resolution_status', 'pending')
                ->count();

            $historicalOptimizationRows = ChoiceOptimizationHistoricalChoice::query()->count();
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
            'historicalPendingReviewCount' => $historicalPendingReviewCount,
            'historicalOptimizationRows' => $historicalOptimizationRows,
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
        ChoiceOptimizationHistoricalStalenessService $staleness,
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

        $staleness->markIfProduced(
            'Historical source Pull/Re-pull changed the Historical Recommendation snapshot.',
            (int) $request->user()->getAuthIdentifier(),
            ['bcs_numbers' => $bcsNumbers->all()],
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
        ExaminationContext $context,
        ChoiceOptimizationHistoricalSource $source,
    ): View {
        $this->assertOptimizationEnabled($settings);

        $status = trim((string) $request->query('status', 'all'));
        $search = trim((string) $request->query('search', ''));

        $matches = $source->matches()
            ->with('registration')
            ->when($status === 'operator_confirmed', fn ($query) => $query->where('resolution_status', 'operator_confirmed'))
            ->when($status !== 'all' && $status !== '' && $status !== 'operator_confirmed', fn ($query) => $query->where('match_status', $status))
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

        $firstReviewMatch = $source->matches()
            ->where('match_status', 'review')
            ->where('resolution_status', 'pending')
            ->orderBy('current_reg')
            ->first();

        $currentBcsNumber = $context->current()?->bcs_number;

        return view('choice-optimization.historical-show', compact(
            'source',
            'matches',
            'status',
            'search',
            'repository',
            'updateAvailable',
            'firstReviewMatch',
            'currentBcsNumber',
        ));
    }

    public function showHistoricalMatch(
        ChoiceOptimizationSettingsService $settings,
        ExaminationContext $context,
        ChoiceOptimizationHistoricalSource $source,
        ChoiceOptimizationHistoricalMatch $match,
    ): View {
        $this->assertOptimizationEnabled($settings);
        abort_unless((int) $match->historical_source_id === (int) $source->id, 404);

        $match->load('registration');
        $nextReviewMatch = $source->matches()
            ->where('match_status', 'review')
            ->where('resolution_status', 'pending')
            ->whereKeyNot($match->id)
            ->orderBy('current_reg')
            ->first();

        $currentBcsNumber = $context->current()?->bcs_number;

        $currentDistrict = null;
        if (filled($match->registration?->district_code)) {
            $currentDistrict = District::query()
                ->where('code', (string) $match->registration->district_code)
                ->first(['code', 'name']);
        }

        $resolvedByUser = $match->resolved_by
            ? User::query()->find((int) $match->resolved_by, ['id', 'name'])
            : null;

        return view('choice-optimization.historical-match-show', compact(
            'source',
            'match',
            'nextReviewMatch',
            'currentBcsNumber',
            'currentDistrict',
            'resolvedByUser',
        ));
    }

    public function resolveHistoricalMatch(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationHistoricalSource $source,
        ChoiceOptimizationHistoricalMatch $match,
        ChoiceOptimizationHistoricalReviewService $service,
    ): RedirectResponse|JsonResponse {
        $this->assertOptimizationEnabled($settings);
        abort_unless((int) $match->historical_source_id === (int) $source->id, 404);

        $validated = $request->validate([
            'decision' => ['required', 'in:confirm,reject'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $service->resolve(
            $source,
            $match,
            (string) $validated['decision'],
            (string) $validated['reason'],
            (int) $request->user()->getAuthIdentifier(),
        );

        $nextReview = $source->matches()
            ->where('match_status', 'review')
            ->where('resolution_status', 'pending')
            ->orderBy('current_reg')
            ->first();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $validated['decision'] === 'confirm'
                    ? 'Historical match confirmed.'
                    : 'Historical match rejected.',
                'remaining_review_rows' => (int) $source->refresh()->review_count,
                'next_review_url' => $nextReview
                    ? route('choice-optimization.historical.matches.show', [$source, $nextReview])
                    : null,
                'source_url' => route('choice-optimization.historical.show', $source),
            ]);
        }

        return $nextReview
            ? redirect()->route('choice-optimization.historical.matches.show', [$source, $nextReview])
                ->with('success', 'Historical match review saved. Continue with the next review.')
            : redirect()->route('choice-optimization.historical.show', $source)
                ->with('success', 'Historical match review saved. All review items are resolved.');
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

    public function queueHistoricalChoiceOptimization(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ExaminationContext $context,
    ): RedirectResponse {
        $this->assertOptimizationEnabled($settings);

        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination is selected.');

        $pendingReview = ChoiceOptimizationHistoricalMatch::query()
            ->where('match_status', 'review')
            ->where('resolution_status', 'pending')
            ->count();

        abort_if(
            $pendingReview > 0,
            409,
            "Resolve all {$pendingReview} pending Historical Match REVIEW item(s) before Historical Choice Optimization."
        );

        $sourcesNotReady = ChoiceOptimizationHistoricalSource::query()
            ->where('status', '<>', 'pulled')
            ->count();

        abort_if(
            $sourcesNotReady > 0,
            409,
            'Every Historical source already added to this workspace must be fully PULLED before Historical Choice Optimization.'
        );

        $state = $settings->state();
        abort_if(
            in_array((string) $state->status, ['historical_optimization_queued', 'historical_optimizing'], true),
            409,
            'Historical Choice Optimization is already running.'
        );

        $from = (string) $state->status;
        $state->update([
            'status' => 'historical_optimization_queued',
            'is_stale' => true,
            'stale_reason' => 'Historical Choice Optimization is queued.',
            'finalized_by' => null,
            'finalized_at' => null,
        ]);

        ChoiceOptimizationProcessingAudit::query()->create([
            'event' => 'HISTORICAL_CHOICE_OPTIMIZATION_QUEUED',
            'actor_id' => (int) $request->user()->getAuthIdentifier(),
            'from_status' => $from,
            'to_status' => 'historical_optimization_queued',
            'context' => [
                'pending_historical_reviews' => $pendingReview,
            ],
            'created_at' => now(),
        ]);

        ProcessChoiceOptimizationHistoricalChoice::dispatch(
            (int) $examId,
            (int) $request->user()->getAuthIdentifier(),
        );

        return redirect()
            ->route('choice-optimization.historical-choices.index')
            ->with('success', 'Historical Choice Optimization queued.');
    }

    public function historicalChoices(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
    ): View {
        $this->assertOptimizationEnabled($settings);

        $status = trim((string) $request->query('status', 'all'));
        $search = trim((string) $request->query('search', ''));

        $rows = ChoiceOptimizationHistoricalChoice::query()
            ->with('registration')
            ->when($status === 'warning', fn ($query) => $query->whereNotNull('warnings'))
            ->when($status === 'blocking', fn ($query) => $query->whereNotNull('blocking_issues'))
            ->when(
                ! in_array($status, ['all', '', 'warning', 'blocking'], true),
                fn ($query) => $query->where('optimization_status', $status)
            )
            ->when($search !== '', fn ($query) => $query->where('reg', 'like', '%'.$search.'%'))
            ->orderBy('reg')
            ->paginate(100)
            ->withQueryString();

        $state = $settings->state();
        $summary = (array) data_get($state->summary, 'historical_choice_optimization', []);
        $choiceCodeAbbrMap = $this->choiceCodeAbbrMap();

        return view('choice-optimization.historical-choices-index', compact(
            'rows',
            'state',
            'status',
            'search',
            'summary',
            'choiceCodeAbbrMap',
        ));
    }

    public function historicalChoiceStatus(
        ChoiceOptimizationSettingsService $settings,
    ): JsonResponse {
        $this->assertOptimizationEnabled($settings);
        $state = $settings->state()->refresh();

        $running = in_array(
            (string) $state->status,
            ['historical_optimization_queued', 'historical_optimizing', 'finalization_queued', 'finalizing'],
            true,
        );

        return response()->json([
            'status' => (string) $state->status,
            'running' => $running,
            'is_stale' => (bool) $state->is_stale,
            'stale_reason' => $state->stale_reason,
            'summary' => (array) data_get($state->summary, 'historical_choice_optimization', []),
        ]);
    }

    public function historicalChoiceShow(
        ChoiceOptimizationSettingsService $settings,
        ChoiceOptimizationHistoricalChoice $choice,
    ): View {
        $this->assertOptimizationEnabled($settings);

        $choice->load('registration');

        $matches = ChoiceOptimizationHistoricalMatch::query()
            ->where('registration_id', (int) $choice->registration_id)
            ->where('match_status', 'matched')
            ->orderBy('previous_bcs_number')
            ->get();

        $choiceCodeAbbrMap = $this->choiceCodeAbbrMap();

        return view('choice-optimization.historical-choice-show', compact(
            'choice',
            'matches',
            'choiceCodeAbbrMap',
        ));
    }

    public function finalizeHistoricalChoices(
        Request $request,
        ChoiceOptimizationSettingsService $settings,
        ExaminationContext $context,
    ): RedirectResponse {
        $this->assertOptimizationEnabled($settings);

        $examId = $context->currentId();
        abort_if($examId === null, 409, 'No examination is selected.');

        $state = $settings->state()->refresh();

        abort_unless(
            (string) $state->status === 'historical_optimized' && ! (bool) $state->is_stale,
            409,
            'Historical Choice Optimization must be current, complete, and free of blocking issues before finalization.'
        );

        $from = (string) $state->status;
        $state->update([
            'status' => 'finalization_queued',
            'finalized_by' => null,
            'finalized_at' => null,
        ]);

        ChoiceOptimizationProcessingAudit::query()->create([
            'event' => 'CHOICE_OPTIMIZATION_FINALIZATION_QUEUED',
            'actor_id' => (int) $request->user()->getAuthIdentifier(),
            'from_status' => $from,
            'to_status' => 'finalization_queued',
            'context' => [
                'dataset_hash' => $state->dataset_hash,
            ],
            'created_at' => now(),
        ]);

        FinalizeChoiceOptimizationHistoricalChoice::dispatch(
            (int) $examId,
            (int) $request->user()->getAuthIdentifier(),
        );

        return redirect()
            ->route('choice-optimization.historical-choices.index')
            ->with('success', 'Finalize Allocation-ready Choice queued.');
    }

    private function choiceCodeAbbrMap(): array
    {
        $map = [];

        foreach (\App\Models\CadreMaster::query()->get(['cadre_code', 'cadre_abbr']) as $cadre) {
            $code = (int) $cadre->cadre_code;
            $abbr = trim((string) $cadre->cadre_abbr);

            if ($code > 0 && $abbr !== '') {
                $map[$code][] = $abbr;
            }
        }

        foreach (\App\Models\CadreSubMaster::query()->get(['sub_cadre_code', 'sub_cadre_abbr']) as $subCadre) {
            $code = (int) $subCadre->sub_cadre_code;
            $abbr = trim((string) $subCadre->sub_cadre_abbr);

            if ($code > 0 && $abbr !== '') {
                $map[$code][] = $abbr;
            }
        }

        return collect($map)
            ->map(fn (array $abbrs): string => collect($abbrs)
                ->filter()
                ->unique()
                ->implode(' / '))
            ->all();
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
