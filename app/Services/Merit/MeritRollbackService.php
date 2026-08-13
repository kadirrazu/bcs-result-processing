<?php

namespace App\Services\Merit;

use App\Models\MeritFinalizationRun;
use App\Models\MeritProcessingRun;
use App\Models\MeritProcessingState;
use App\Models\MeritResult;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MeritRollbackService
{
    public function __construct(
        private readonly MeritReadinessService $readiness,
        private readonly MeritAuditService $audit,
        private readonly MeritSourceSnapshotComparator $snapshots,
        private readonly MeritDatasetHasher $hasher,
    ) {}

    public function rollback(
        MeritFinalizationRun $target,
        User $actor,
        string $confirmation,
        ?string $reason = null,
    ): MeritFinalizationRun {
        if ($confirmation !== 'ROLLBACK') {
            throw ValidationException::withMessages([
                'confirmation' => 'Type ROLLBACK exactly to restore this finalized Merit version.',
            ]);
        }

        if ($target->status === 'current') {
            throw ValidationException::withMessages([
                'confirmation' => 'The selected Merit finalization is already current.',
            ]);
        }

        $ready = $this->readiness->assertReady();
        if (! $this->snapshots->equivalent($target->source_snapshot, $ready['source_snapshot'])) {
            throw ValidationException::withMessages([
                'confirmation' => 'MERIT_ROLLBACK_SOURCE_MISMATCH: This historical Merit version was produced from different finalized Circular/Tabulation/Choice datasets. Re-generation is mandatory.',
            ]);
        }

        if (MeritProcessingRun::query()->whereIn('status', ['queued', 'running'])->exists()) {
            throw ValidationException::withMessages([
                'confirmation' => 'A Merit Generation run is active. Complete it before rollback.',
            ]);
        }

        $run = MeritProcessingRun::query()->findOrFail($target->processing_run_id);
        if ($run->status !== 'completed') {
            throw ValidationException::withMessages([
                'confirmation' => 'Only a completed historical Merit run can be restored.',
            ]);
        }

        $resultCount = MeritResult::query()->where('processing_run_id', $run->id)->count();
        if ($resultCount !== (int) $run->processed_rows) {
            throw ValidationException::withMessages([
                'confirmation' => 'MERIT_ROLLBACK_RESULT_COUNT_MISMATCH: Historical Merit rows are incomplete.',
            ]);
        }

        $freshHash = $this->hasher->hash($run->id);
        if (! hash_equals((string) $target->dataset_hash, $freshHash)) {
            throw ValidationException::withMessages([
                'confirmation' => 'MERIT_ROLLBACK_DATASET_HASH_MISMATCH: Historical Merit data no longer matches its finalized hash.',
            ]);
        }

        return DB::connection('exam')->transaction(function () use ($target, $actor, $reason, $run, $freshHash): MeritFinalizationRun {
            $state = MeritProcessingState::query()->lockForUpdate()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
            $before = (string) $state->status;

            MeritFinalizationRun::query()->where('status', 'current')->update(['status' => 'superseded']);
            $target->update(['status' => 'current']);

            $state->update([
                'status' => 'finalized',
                'latest_run_id' => $run->id,
                'latest_finalization_run_id' => $target->id,
                'is_stale' => false,
                'stale_reason' => null,
                'source_snapshot' => $target->source_snapshot,
                'dataset_hash' => $freshHash,
                'summary' => $target->summary,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
            ]);

            $this->audit->record(
                'MERIT_ROLLED_BACK',
                $actor,
                $before,
                'finalized',
                $reason ?: 'Restored a hash-verified compatible historical Merit version.',
                [
                    'restored_finalization_run_id' => $target->id,
                    'processing_run_id' => $run->id,
                    'processing_version' => $run->processing_version,
                    'dataset_hash' => $freshHash,
                ],
                $run->id,
            );

            return $target->refresh();
        }, 3);
    }
}
