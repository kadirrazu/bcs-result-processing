<?php

namespace App\Services\ChoiceOptimization;

use App\Enums\WrittenCandidateStatus;
use App\Enums\WrittenProcessingStatus;
use App\Models\ChoiceOptimizationOmrBatch;
use App\Models\ChoiceOptimizationOmrStaging;
use App\Models\Registration;
use App\Models\WrittenProcessingState;
use App\Models\WrittenResult;
use App\Services\ChoiceValidation\ChoiceValidationEngine;
use App\Services\Circular\CircularFinalizedDatasetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use stdClass;
use Throwable;

final class ChoiceOptimizationOmrValidationService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly ChoiceValidationEngine $engine,
    ) {}

    public function validate(int $batchId): ChoiceOptimizationOmrBatch
    {
        $batch = ChoiceOptimizationOmrBatch::query()->findOrFail($batchId);
        $this->assertWrittenReady();
        $entries = $this->circular->entries();
        $this->engine->prepare($entries);

        $batch->update([
            'status' => 'validating', 'processed_rows' => 0, 'valid_rows' => 0,
            'invalid_rows' => 0, 'conflict_rows' => 0, 'review_rows' => 0,
            'progress_percent' => 0, 'failure_message' => null, 'finished_at' => null,
        ]);

        try {
            $rows = ChoiceOptimizationOmrStaging::query()->where('batch_id', $batchId)->orderBy('id')->get();
            $regs = $rows->pluck('effective_reg')->filter()->unique()->values();
            $written = WrittenResult::query()
                ->whereIn('reg', $regs)
                ->where('status', WrittenCandidateStatus::Active->value)
                ->whereNotNull('written_qualified_track')
                ->get(['id', 'registration_id', 'reg', 'written_qualified_track'])
                ->groupBy(fn (WrittenResult $row) => (string) $row->reg);
            $registrationIds = $written->flatten()->pluck('registration_id')->filter()->unique()->values();
            $registrations = Registration::query()->whereIn('id', $registrationIds)->get()->keyBy('id');
            $duplicateCounts = $rows->filter(fn ($row) => filled($row->effective_reg))
                ->countBy(fn ($row) => (string) $row->effective_reg);

            $valid = $invalid = $conflicts = $reviews = $processed = 0;
            $chunkSize = max(100, (int) config('choice-optimization.import_chunk_size', 1000));

            foreach ($rows->chunk($chunkSize) as $chunk) {
                DB::connection('exam')->transaction(function () use ($chunk, $written, $registrations, $duplicateCounts, $entries, &$valid, &$invalid, &$conflicts, &$reviews, &$processed): void {
                    foreach ($chunk as $row) {
                        $errors = [];
                        $warnings = [];
                        $status = 'valid';
                        $reg = trim((string) ($row->effective_reg ?? ''));
                        $rawDecision = strtoupper(trim((string) ($row->change_choice ?? '')));
                        $effectiveDecision = strtoupper(trim((string) ($row->effective_change_choice ?? '')));

                        if ($reg === '') {
                            $errors[] = ['code' => 'OMR_REGISTRATION_REQUIRED', 'message' => 'OMR registration is required.'];
                        }

                        $matches = $reg !== '' ? ($written->get($reg) ?? collect()) : collect();
                        if ($reg !== '' && $matches->count() === 0) {
                            $errors[] = ['code' => 'INVALID_OMR_REGISTRATION', 'message' => 'Registration does not match the finalized Written-qualified candidate population.'];
                        } elseif ($matches->count() > 1) {
                            $errors[] = ['code' => 'WRITTEN_REGISTRATION_AMBIGUOUS', 'message' => 'Registration resolves to multiple Written-qualified candidates and requires resolution.'];
                            $status = 'conflict';
                        }

                        if ($reg !== '' && (int) ($duplicateCounts[$reg] ?? 0) > 1) {
                            $errors[] = ['code' => 'DUPLICATE_OMR_REGISTRATION', 'message' => 'The same registration is claimed by multiple OMR rows. Resolve the registration conflict before override processing.'];
                            $status = 'conflict';
                        }

                        if (! in_array($rawDecision, ['YES', 'NO'], true)) {
                            $errors[] = ['code' => 'INVALID_CHANGE_CHOICE_DECISION', 'message' => 'change_choice must be YES or NO.'];
                        }

                        if ($rawDecision === 'NO' && (int) $row->raw_choice_count > 0 && ! $row->decision_resolution) {
                            $warnings[] = [
                                'code' => 'NO_WITH_CHOICES_REQUIRES_OPERATOR_DECISION',
                                'message' => 'OMR says NO but contains choices. Compare the finalized validated choice with the OMR options and choose how to interpret the form.',
                            ];
                            if ($status !== 'conflict') {
                                $status = 'decision_review';
                            }
                        }

                        if ($rawDecision === 'YES' && (int) $row->raw_choice_count < 1) {
                            $errors[] = ['code' => 'YES_REQUIRES_CHOICE', 'message' => 'change_choice=YES requires at least one new OMR choice.'];
                        }

                        if ($effectiveDecision === '') {
                            if ($rawDecision === 'YES') $effectiveDecision = 'YES';
                            elseif ($rawDecision === 'NO' && (int) $row->raw_choice_count === 0) $effectiveDecision = 'NO';
                            elseif ($row->decision_resolution === 'consider_no_as_yes_keep_options') $effectiveDecision = 'YES';
                            elseif ($row->decision_resolution === 'keep_no_discard_options') $effectiveDecision = 'NO';
                        }

                        $match = $matches->count() === 1 ? $matches->first() : null;
                        $registration = $match ? $registrations->get((int) $match->registration_id) : null;
                        $validatedCodes = null;
                        $choiceDetails = null;
                        $choiceStatus = 'not_started';

                        if ($status !== 'conflict' && $errors === [] && $status !== 'decision_review' && $match && $registration) {
                            if ($effectiveDecision === 'YES') {
                                $track = $this->track($this->enumValue($match->written_qualified_track) ?? '');
                                $items = $this->choiceItems((array) $row->raw_choices);
                                $output = $this->engine->validate($registration, $track, $items, $entries);
                                $validatedCodes = $output['validated'];
                                $choiceDetails = $output['details'];
                                $choiceStatus = $output['status'] === 'valid' ? 'valid' : 'invalid';
                                if ($choiceStatus !== 'valid') {
                                    $errors[] = [
                                        'code' => 'OMR_CHOICE_VALIDATION_FAILED',
                                        'message' => 'No valid OMR override choice remains after applying the finalized Choice Validation rules.',
                                    ];
                                }
                            } elseif ($effectiveDecision === 'NO') {
                                $choiceStatus = 'not_applicable';
                            }
                        }

                        if ($status !== 'conflict' && $status !== 'decision_review' && $errors !== []) {
                            $status = 'invalid';
                        }

                        $row->update([
                            'registration_id' => $match?->registration_id,
                            'written_qualified_track' => $match ? $this->enumValue($match->written_qualified_track) : null,
                            'effective_change_choice' => $effectiveDecision !== '' ? $effectiveDecision : null,
                            'choice_validation_status' => $choiceStatus,
                            'validated_omr_choice_codes' => $validatedCodes,
                            'choice_validation_details' => $choiceDetails,
                            'validation_status' => $status,
                            'validation_errors' => $errors !== [] ? $errors : null,
                            'validation_warnings' => $warnings !== [] ? $warnings : null,
                            'resolution_status' => $status === 'conflict' ? 'review_required' : ($row->resolution_status === 'resolved' ? 'resolved' : 'not_required'),
                        ]);

                        $processed++;
                        if ($status === 'valid') $valid++;
                        elseif ($status === 'conflict') $conflicts++;
                        elseif ($status === 'decision_review') $reviews++;
                        else $invalid++;
                    }
                });

                $batch->update([
                    'processed_rows' => $processed,
                    'valid_rows' => $valid,
                    'invalid_rows' => $invalid,
                    'conflict_rows' => $conflicts,
                    'review_rows' => $reviews,
                    'progress_percent' => $rows->count() > 0 ? round($processed * 100 / $rows->count(), 4) : 100,
                ]);
            }

            $batch->update([
                'status' => ($invalid > 0 || $conflicts > 0 || $reviews > 0) ? 'needs_review' : 'validated',
                'processed_rows' => $processed, 'valid_rows' => $valid, 'invalid_rows' => $invalid,
                'conflict_rows' => $conflicts, 'review_rows' => $reviews, 'progress_percent' => 100,
                'validated_at' => now(), 'finished_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $e) {
            $batch->update(['status' => 'validation_failed', 'failure_message' => mb_substr($e->getMessage(), 0, 65000), 'finished_at' => now()]);
            throw $e;
        }
    }

    private function assertWrittenReady(): void
    {
        $state = WrittenProcessingState::query()->first();
        $ready = $state
            && $state->status === WrittenProcessingStatus::ResultFinalized
            && ! (bool) $state->is_stale
            && $state->result_finalized_at;

        if (! $ready) {
            throw ValidationException::withMessages([
                'omr' => 'A current, non-stale finalized Written result is required before OMR identity validation.',
            ]);
        }
    }

    private function track(string $writtenTrack): array
    {
        $writtenTrack = strtoupper(trim($writtenTrack));
        $track = match ($writtenTrack) {
            'GG', 'GN' => 'general',
            'TT', 'T' => 'technical',
            'GT' => 'both',
            default => null,
        };

        return [
            'eligible' => $track !== null,
            'track' => $track,
            'written_track' => $writtenTrack,
            'status' => $track !== null ? 'valid' : 'not_applicable_due_to_unresolved_written_track',
            'reason_code' => $track !== null ? null : 'CANDIDATE_UNRESOLVED_WRITTEN_TRACK',
            'reason_message' => $track !== null ? null : 'Written-qualified track could not be resolved for OMR choice validation.',
        ];
    }

    /** @return array<int,stdClass> */
    private function choiceItems(array $choices): array
    {
        $items = [];
        $position = 0;
        foreach ($choices as $column => $code) {
            $code = trim((string) ($code ?? ''));
            if ($code === '') continue;
            $position++;
            $item = new stdClass();
            $item->position = $position;
            $item->source_column = (string) $column;
            $item->choice_code = $code;
            $items[] = $item;
        }
        return $items;
    }

    private function enumValue(mixed $value): ?string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : ($value !== null ? (string) $value : null);
    }
}
