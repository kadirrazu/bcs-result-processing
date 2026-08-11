<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceSource;
use App\Models\ChoiceValidationImportBatch;
use App\Models\ChoiceValidationProcessingAudit;
use App\Models\ChoiceValidationProcessingState;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ChoiceSourceApprovalService
{
    public function __construct(private readonly ChoiceColumnResolver $columns) {}

    /**
     * Approve every currently valid, not-yet-approved staging row.
     * Invalid rows remain in the same batch for the correction loop and do not
     * block approval of valid rows.
     *
     * @return array{source_version:int,newly_approved:int,total_approved:int,pending_invalid:int,source_complete:bool}
     */
    public function approve(ChoiceValidationImportBatch $batch, Authenticatable $actor): array
    {
        if (! in_array((string) $batch->status, ['validated', 'partially_approved'], true)) {
            throw new RuntimeException('Only a validated Choice source batch can approve valid rows.');
        }
        if ((int) $batch->valid_rows < 1) {
            throw new RuntimeException('Choice source approval requires at least one valid row.');
        }

        return DB::connection('exam')->transaction(function () use ($batch, $actor): array {
            $state = ChoiceValidationProcessingState::query()
                ->lockForUpdate()
                ->firstOrCreate(['id' => 1], ['status' => 'not_started']);

            $sourceVersion = (int) ($batch->source_version ?: (((int) $state->current_source_version) + 1));
            $existingSourceRows = ChoiceSource::query()
                ->where('source_batch_id', $batch->id)
                ->pluck('source_row')
                ->filter(static fn ($value) => $value !== null)
                ->map(static fn ($value) => (int) $value)
                ->all();

            $query = $batch->stagingRows()->where('validation_status', 'valid');
            if ($existingSourceRows !== []) {
                $query->whereNotIn('source_row', $existingSourceRows);
            }

            $newlyApproved = 0;
            $chunk = max(100, (int) config('choice-validation.approval_chunk_size', 1000));

            $query->orderBy('id')->chunkById($chunk, function ($rows) use (
                $batch,
                $actor,
                $sourceVersion,
                &$newlyApproved,
            ): void {
                foreach ($rows as $row) {
                    $source = ChoiceSource::query()->create([
                        'registration_id' => $row->registration_id,
                        'user_id' => $row->user_id,
                        'reg' => $row->reg,
                        'source_version' => $sourceVersion,
                        'source_batch_id' => $batch->id,
                        'source_row' => $row->source_row,
                        'source_snapshot' => $row->raw_payload,
                        'raw_choice_count' => $row->raw_choice_count,
                        'approved_by' => $actor->getAuthIdentifier(),
                        'approved_at' => now(),
                    ]);

                    $raw = is_array($row->raw_choices)
                        ? $row->raw_choices
                        : (json_decode((string) $row->raw_choices, true) ?: []);
                    $items = [];
                    $timestamp = now()->format('Y-m-d H:i:s');
                    foreach ($this->columns->choiceColumns((int) $batch->configured_maximum_choices) as $positionZero => $column) {
                        $value = trim((string) ($raw[$column] ?? ''));
                        if ($value === '') {
                            continue;
                        }
                        if (preg_match('/^\d+\.0$/', $value) === 1) {
                            $value = substr($value, 0, -2);
                        }
                        $items[] = [
                            'choice_validation_source_id' => $source->id,
                            'position' => $positionZero + 1,
                            'source_column' => $column,
                            'raw_value' => (string) ($raw[$column] ?? ''),
                            'choice_code' => strtoupper($value),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                    if ($items !== []) {
                        DB::connection('exam')->table('choice_validation_source_items')->insert($items);
                    }
                    $newlyApproved++;
                }
            });

            if ($newlyApproved < 1) {
                throw new RuntimeException('There are no new valid Choice source rows waiting for approval.');
            }

            $totalApproved = ChoiceSource::query()->where('source_batch_id', $batch->id)->count();
            $pendingInvalid = (int) $batch->invalid_rows;
            $sourceComplete = $pendingInvalid === 0;
            $batchStatus = $sourceComplete ? 'approved' : 'partially_approved';
            $stateStatus = $sourceComplete ? 'source_approved' : 'source_partially_approved';
            $hadValidation = (int) $state->current_validation_version > 0;

            $batch->update([
                'source_version' => $sourceVersion,
                'status' => $batchStatus,
                'approved_rows' => $totalApproved,
                'approved_by' => $actor->getAuthIdentifier(),
                'approved_at' => now(),
                'finished_at' => now(),
            ]);

            $state->update([
                'status' => $stateStatus,
                'current_source_version' => $sourceVersion,
                'approved_source_version' => $sourceVersion,
                'is_stale' => $hadValidation,
                'stale_reason' => $hadValidation
                    ? 'Approved Choice source rows changed after Choice Validation. Re-run Choice Validation.'
                    : null,
                'finalized_validation_version' => $hadValidation ? null : $state->finalized_validation_version,
                'latest_finalization_run_id' => $hadValidation ? null : $state->latest_finalization_run_id,
                'finalized_at' => $hadValidation ? null : $state->finalized_at,
                'summary' => array_merge((array) $state->summary, [
                    'source' => [
                        'batch_id' => (int) $batch->id,
                        'source_version' => $sourceVersion,
                        'approved_source_rows' => $totalApproved,
                        'pending_invalid_rows' => $pendingInvalid,
                        'source_complete' => $sourceComplete,
                        'maximum_allowed_choices' => (int) $batch->configured_maximum_choices,
                    ],
                ]),
            ]);

            ChoiceValidationProcessingAudit::query()->create([
                'action' => $sourceComplete ? 'CHOICE_SOURCE_VALID_ROWS_APPROVED' : 'CHOICE_SOURCE_VALID_ROWS_PARTIALLY_APPROVED',
                'actor_id' => $actor->getAuthIdentifier(),
                'actor_name' => $actor->name ?? null,
                'reason' => $sourceComplete
                    ? 'Approved/merged all currently valid Choice source rows; source batch is complete.'
                    : 'Approved/merged currently valid Choice source rows while invalid rows remain pending correction.',
                'summary' => [
                    'source_version' => $sourceVersion,
                    'newly_approved' => $newlyApproved,
                    'total_approved' => $totalApproved,
                    'pending_invalid' => $pendingInvalid,
                    'source_complete' => $sourceComplete,
                    'batch_id' => (int) $batch->id,
                ],
                'batch_id' => $batch->id,
                'created_at' => now(),
            ]);

            return [
                'source_version' => $sourceVersion,
                'newly_approved' => $newlyApproved,
                'total_approved' => $totalApproved,
                'pending_invalid' => $pendingInvalid,
                'source_complete' => $sourceComplete,
            ];
        });
    }
}
