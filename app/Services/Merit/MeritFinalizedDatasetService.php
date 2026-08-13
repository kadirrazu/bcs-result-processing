<?php

namespace App\Services\Merit;

use App\Models\MeritFinalizationRun;
use App\Models\MeritProcessingState;
use Illuminate\Validation\ValidationException;

final class MeritFinalizedDatasetService
{
    public function __construct(private readonly MeritDatasetHasher $hasher) {}

    public function verifiedFinalizationRun(): MeritFinalizationRun
    {
        $state = MeritProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);

        if ((string) $state->status !== 'finalized' || (bool) $state->is_stale || ! $state->latest_finalization_run_id) {
            throw ValidationException::withMessages([
                'merit' => 'A current, non-stale finalized Merit dataset is required.',
            ]);
        }

        $final = MeritFinalizationRun::query()->find($state->latest_finalization_run_id);
        if (! $final) {
            throw ValidationException::withMessages(['merit' => 'Merit finalization record could not be resolved.']);
        }

        $currentHash = $this->hasher->hash((int) $final->processing_run_id);
        if (! hash_equals((string) $final->dataset_hash, $currentHash)) {
            throw ValidationException::withMessages([
                'merit' => 'MERIT_DATASET_HASH_MISMATCH: Finalized Merit rows no longer match the finalized dataset hash. Regenerate and finalize Merit.',
            ]);
        }

        return $final;
    }

    /** @return array<string,mixed> */
    public function verifiedSummary(): array
    {
        $final = $this->verifiedFinalizationRun();

        return [
            'processing_run_id' => (int) $final->processing_run_id,
            'processing_version' => (int) $final->processing_version,
            'dataset_hash' => (string) $final->dataset_hash,
            'summary' => (array) $final->summary,
            'finalized_at' => $final->finalized_at,
        ];
    }
}
