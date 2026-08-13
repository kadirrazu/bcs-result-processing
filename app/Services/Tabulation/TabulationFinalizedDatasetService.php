<?php

namespace App\Services\Tabulation;

use App\Models\TabulationFinalizationRun;
use App\Models\TabulationProcessingState;
use Illuminate\Validation\ValidationException;

final class TabulationFinalizedDatasetService
{
    public function __construct(private readonly TabulationDatasetHasher $hasher) {}

    public function verifiedFinalizationRun(): TabulationFinalizationRun
    {
        $state = TabulationProcessingState::query()->first();

        if (! $state
            || (string) $state->status !== 'finalized'
            || (bool) $state->is_stale
            || ! $state->latest_run_id
            || ! $state->latest_finalization_run_id
        ) {
            throw ValidationException::withMessages([
                'tabulation' => 'A current, non-stale finalized Tabulation dataset is required.',
            ]);
        }

        $finalization = TabulationFinalizationRun::query()
            ->whereKey($state->latest_finalization_run_id)
            ->where('status', 'current')
            ->first();

        if (! $finalization || (int) $finalization->processing_run_id !== (int) $state->latest_run_id) {
            throw ValidationException::withMessages([
                'tabulation' => 'Current Tabulation finalization record could not be resolved.',
            ]);
        }

        $currentHash = $this->hasher->hash((int) $finalization->processing_run_id);
        $storedHash = (string) ($finalization->dataset_hash ?? '');

        if ($storedHash === '' || ! hash_equals($storedHash, $currentHash)) {
            throw ValidationException::withMessages([
                'tabulation' => 'TABULATION_DATASET_HASH_MISMATCH: Finalized Tabulation data no longer matches its stored dataset hash. Regenerate and finalize Tabulation again.',
            ]);
        }

        return $finalization;
    }

    /** @return array<string,mixed> */
    public function verifiedSummary(): array
    {
        $run = $this->verifiedFinalizationRun();

        return [
            'ready' => true,
            'processing_run_id' => (int) $run->processing_run_id,
            'processing_version' => (int) $run->processing_version,
            'dataset_hash' => (string) $run->dataset_hash,
            'summary' => (array) $run->summary,
            'finalized_at' => $run->finalized_at,
        ];
    }
}
