<?php

namespace App\Services\Tabulation;

use App\Models\TabulationFinalizationRun;
use App\Models\TabulationProcessingState;
use Throwable;

final class TabulationDatasetIntegrityService
{
    public function __construct(private readonly TabulationDatasetHasher $hasher) {}

    /** @return array<string,mixed> */
    public function inspect(?int $runId = null, bool $recompute = true): array
    {
        $state = TabulationProcessingState::query()->first();
        $storedHash = (string) ($state?->dataset_hash ?? '');

        if (! $state || (string) $state->status !== 'finalized' || (bool) $state->is_stale) {
            return [
                'verified' => false,
                'status' => $state?->is_stale ? 'STALE' : 'NOT_FINALIZED',
                'stored_hash' => $storedHash !== '' ? $storedHash : null,
                'current_hash' => null,
                'processing_run_id' => $state?->latest_run_id,
                'processing_version' => null,
                'finalized_at' => $state?->finalized_at,
                'detail' => $state?->is_stale
                    ? (string) ($state->stale_reason ?: 'Finalized Tabulation is stale.')
                    : 'A current finalized Tabulation dataset is required before hash verification.',
            ];
        }

        $finalization = TabulationFinalizationRun::query()
            ->whereKey($state->latest_finalization_run_id)
            ->where('status', 'current')
            ->first();

        if (! $finalization) {
            return [
                'verified' => false,
                'status' => 'FINALIZATION_RECORD_MISSING',
                'stored_hash' => $storedHash !== '' ? $storedHash : null,
                'current_hash' => null,
                'processing_run_id' => $state->latest_run_id,
                'processing_version' => null,
                'finalized_at' => $state->finalized_at,
                'detail' => 'Current Tabulation finalization record could not be resolved.',
            ];
        }

        if ($runId !== null && (int) $finalization->processing_run_id !== $runId) {
            return [
                'verified' => false,
                'status' => 'NOT_CURRENT_FINALIZED_RUN',
                'stored_hash' => (string) ($finalization->dataset_hash ?: $storedHash),
                'current_hash' => null,
                'processing_run_id' => (int) $finalization->processing_run_id,
                'processing_version' => (int) $finalization->processing_version,
                'finalized_at' => $finalization->finalized_at,
                'detail' => 'The selected Tabulation run is not the current finalized dataset.',
            ];
        }

        $finalizedHash = (string) ($finalization->dataset_hash ?: $storedHash);

        if (! $recompute) {
            return [
                'verified' => $finalizedHash !== '',
                'status' => $finalizedHash !== '' ? 'HASH_VERIFIED_AT_FINALIZATION' : 'HASH_MISSING',
                'stored_hash' => $finalizedHash !== '' ? $finalizedHash : null,
                'current_hash' => null,
                'processing_run_id' => (int) $finalization->processing_run_id,
                'processing_version' => (int) $finalization->processing_version,
                'finalized_at' => $finalization->finalized_at,
                'detail' => $finalizedHash !== ''
                    ? 'Dataset hash was generated and verified during Tabulation finalization. A fresh full re-hash is performed at downstream Merit/Allocation readiness gates.'
                    : 'Finalized Tabulation hash is missing.',
            ];
        }

        try {
            $currentHash = $this->hasher->hash((int) $finalization->processing_run_id);
        } catch (Throwable $e) {
            return [
                'verified' => false,
                'status' => 'HASH_VERIFICATION_FAILED',
                'stored_hash' => (string) ($finalization->dataset_hash ?: $storedHash),
                'current_hash' => null,
                'processing_run_id' => (int) $finalization->processing_run_id,
                'processing_version' => (int) $finalization->processing_version,
                'finalized_at' => $finalization->finalized_at,
                'detail' => $e->getMessage(),
            ];
        }

        $verified = $finalizedHash !== '' && hash_equals($finalizedHash, $currentHash);

        return [
            'verified' => $verified,
            'status' => $verified ? 'HASH_VERIFIED' : 'HASH_MISMATCH',
            'stored_hash' => $finalizedHash !== '' ? $finalizedHash : null,
            'current_hash' => $currentHash,
            'processing_run_id' => (int) $finalization->processing_run_id,
            'processing_version' => (int) $finalization->processing_version,
            'finalized_at' => $finalization->finalized_at,
            'detail' => $verified
                ? 'Stored finalized hash matches the current Tabulation dataset.'
                : 'TABULATION_DATASET_HASH_MISMATCH: Stored finalized hash does not match the current Tabulation dataset.',
        ];
    }
}
