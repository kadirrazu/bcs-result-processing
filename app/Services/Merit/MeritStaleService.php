<?php
namespace App\Services\Merit;

use App\Models\MeritProcessingState;
use App\Services\Dependencies\DownstreamStalePropagationService;
use Throwable;

final class MeritStaleService
{
    public function __construct(
        private readonly MeritReadinessService $readiness,
        private readonly MeritSourceSnapshotComparator $snapshots,
        private readonly DownstreamStalePropagationService $downstream,
    ) {}

    /**
     * Lightweight stale synchronization for page requests.
     * Stored finalized version/hash metadata is compared here; full live dataset
     * hashes are recomputed by MeritReadinessService::assertReady() before Generate,
     * queued processing and Finalize.
     *
     * IMPORTANT: discovering stale Merit is itself an Allocation-authority change.
     * Therefore lazy detection must propagate to A2 -> A3 -> A4 -> A5 as well;
     * otherwise historical Allocation/Reporting could remain falsely current.
     */
    public function synchronize(?array $inspection = null): MeritProcessingState
    {
        $state = MeritProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        if (! $state->source_snapshot) {
            return $state;
        }

        $reason = null;
        try {
            $inspection ??= $this->readiness->inspect();
            if (! $inspection['ready']) {
                throw new \RuntimeException('One or more upstream finalized datasets are not ready.');
            }

            $current = $inspection['source_snapshot'];
            if (! $this->snapshots->equivalent($state->source_snapshot, $current)) {
                $reason = 'MERIT_UPSTREAM_DATASET_CHANGED: Circular or Tabulation stored finalized hash/version no longer matches this Merit run.';
            }
        } catch (Throwable $e) {
            $reason = 'MERIT_UPSTREAM_NOT_READY: '.$e->getMessage();
        }

        if ($reason !== null) {
            $state->update([
                'is_stale' => true,
                'status' => 'stale',
                'stale_reason' => $reason,
            ]);

            // Idempotent enough for recovery paths: AllocationRunStaleService preserves
            // historical evidence and only marks current authority stale. This also repairs
            // projects where Merit had already been marked stale before this coupling existed.
            $this->downstream->propagate('merit', $reason);
        }

        return $state->refresh();
    }
}
