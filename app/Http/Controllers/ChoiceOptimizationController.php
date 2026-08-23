<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessChoiceOptimizationOmrApproval;
use App\Jobs\ProcessChoiceOptimizationOmrValidation;
use App\Models\ChoiceOptimizationOmrBatch;
use App\Models\ChoiceOptimizationOmrStaging;
use App\Models\ChoiceValidationResult;
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

        return view('choice-optimization.index', [
            'setting' => $setting,
            'state' => $settings->state(),
            'latestOmrBatch' => $latestOmrBatch,
            'omrBatches' => $setting->optimization_enabled
                ? ChoiceOptimizationOmrBatch::query()->latest('id')->limit(10)->get()
                : collect(),
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
            ->when($status !== '' && $status !== 'all', fn ($q) => $q->where('validation_status', $status))
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
                    'category_code' => is_object($category) && method_exists($category, 'code') ? $category->code() : (string) ($category ?? ''),
                    'category_label' => is_object($category) && method_exists($category, 'label') ? $category->label() : '',
                ];
            }
        }

        $remainingOperatorReviews = $this->remainingOmrOperatorReviews((int) $batch->id);

        return view('choice-optimization.omr-show', compact(
            'batch', 'rows', 'status', 'search', 'validatedChoiceMap', 'registrationChoiceMap', 'candidateContextMap',
            'remainingOperatorReviews'
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
