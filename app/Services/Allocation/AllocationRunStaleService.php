<?php

namespace App\Services\Allocation;

use App\Models\AllocationA4Run;
use App\Models\AllocationA5Run;
use App\Models\AllocationInputFreeze;
use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\AllocationRun;
use App\Models\AllocationSeatBreakupVersion;
use Illuminate\Support\Facades\DB;

/**
 * Central Allocation lineage/currentness service.
 *
 * IMPORTANT:
 * - Result/ledger/event evidence is never deleted or rewritten here.
 * - Staleness is metadata saying that an otherwise valid historical result no
 *   longer represents the current authoritative Allocation inputs.
 * - A4 is downstream of one exact A3 run, so stale A3 always makes its A4 stale.
 */
final class AllocationRunStaleService
{
    public function __construct(private readonly AllocationSettingsService $settings) {}

    public function staleA3AndA4(string $reason, ?int $actorId = null): void
    {
        DB::connection('exam')->transaction(function () use ($reason, $actorId): void {
            $now = now();

            $a3Ids = AllocationRun::query()
                ->where('status', 'phase1_complete')
                ->where('is_stale', false)
                ->pluck('id');

            if ($a3Ids->isNotEmpty()) {
                AllocationRun::query()->whereIn('id', $a3Ids)->update([
                    'is_stale' => true,
                    'stale_reason' => $reason,
                    'staled_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $a4Ids = AllocationA4Run::query()
                ->where('status', 'a4_complete')
                ->where('is_stale', false)
                ->pluck('id');

            if ($a4Ids->isNotEmpty()) {
                AllocationA4Run::query()->whereIn('id', $a4Ids)->update([
                    'is_stale' => true,
                    'stale_reason' => $reason,
                    'staled_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $a5Ids = AllocationA5Run::query()
                ->whereIn('status', ['validated_ok','validated_failed','finalized'])
                ->where('is_stale', false)
                ->pluck('id');
            if ($a5Ids->isNotEmpty()) {
                AllocationA5Run::query()->whereIn('id', $a5Ids)->update([
                    'is_stale' => true,
                    'stale_reason' => $reason,
                    'staled_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($a3Ids->isNotEmpty() || $a4Ids->isNotEmpty() || $a5Ids->isNotEmpty()) {
                AllocationProcessingAudit::query()->create([
                    'event' => 'ALLOCATION_PHASE_RESULTS_STALED',
                    'actor_id' => $actorId,
                    'from_status' => null,
                    'to_status' => 'stale',
                    'context' => [
                        'reason' => $reason,
                        'a3_run_ids' => $a3Ids->values()->all(),
                        'a4_run_ids' => $a4Ids->values()->all(),
                        'a5_run_ids' => $a5Ids->values()->all(),
                    ],
                    'created_at' => $now,
                ]);
            }
        });
    }

    /**
     * A successfully completed A3 re-run becomes the single current A3 authority.
     * Older completed A3 runs remain immutable evidence but are excluded from
     * current lineage by stale/superseded metadata.
     */
    public function supersedeEarlierA3ForNewA3(AllocationRun $currentA3, ?int $actorId = null): void
    {
        DB::connection('exam')->transaction(function () use ($currentA3, $actorId): void {
            $now = now();
            $reason = "A3 Phase-1 was re-run and current source is now v{$currentA3->version}. This earlier A3 result is historical/superseded.";

            $ids = AllocationRun::query()
                ->where('status', 'phase1_complete')
                ->where('is_stale', false)
                ->whereKeyNot((int) $currentA3->id)
                ->pluck('id');

            if ($ids->isEmpty()) {
                return;
            }

            AllocationRun::query()->whereIn('id', $ids)->update([
                'is_stale' => true,
                'stale_reason' => $reason,
                'staled_at' => $now,
                'updated_at' => $now,
            ]);

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A3_SUPERSEDED_BY_A3_RERUN',
                'actor_id' => $actorId,
                'from_status' => 'phase1_complete',
                'to_status' => 'stale',
                'context' => [
                    'current_a3_run_id' => (int) $currentA3->id,
                    'current_a3_version' => (int) $currentA3->version,
                    'superseded_a3_run_ids' => $ids->values()->all(),
                    'reason' => $reason,
                ],
                'created_at' => $now,
            ]);
        });
    }

    public function staleA4ForNewA3(AllocationRun $currentA3, ?int $actorId = null): void
    {
        DB::connection('exam')->transaction(function () use ($currentA3, $actorId): void {
            $now = now();
            $reason = "A3 Phase-1 was re-run and current source is now v{$currentA3->version}. Re-run A4 Phase-2.";

            $ids = AllocationA4Run::query()
                ->where('status', 'a4_complete')
                ->where('is_stale', false)
                ->where('phase1_run_id', '<>', (int) $currentA3->id)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                AllocationA4Run::query()->whereIn('id', $ids)->update([
                    'is_stale' => true,
                    'stale_reason' => $reason,
                    'staled_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            /*
             * IMPORTANT:
             * Do NOT stale every A5 merely because reconcileCurrentness() is
             * evaluating the current A3. An A5 bound to a current A4 that itself
             * belongs to this exact A3 is still perfectly current.
             *
             * Only A5 rows whose bound A4 is no longer in the exact current-A3
             * lineage are stale.
             */
            $validA4Ids = AllocationA4Run::query()
                ->where('status', 'a4_complete')
                ->where('is_stale', false)
                ->where('phase1_run_id', (int) $currentA3->id)
                ->pluck('id');

            $a5Query = AllocationA5Run::query()
                ->whereIn('status', ['validated_ok','validated_failed','finalized'])
                ->where('is_stale', false);

            if ($validA4Ids->isNotEmpty()) {
                $a5Query->whereNotIn('allocation_a4_run_id', $validA4Ids);
            }

            $a5Ids = $a5Query->pluck('id');
            if ($a5Ids->isNotEmpty()) {
                AllocationA5Run::query()->whereIn('id', $a5Ids)->update([
                    'is_stale' => true,
                    'stale_reason' => $reason,
                    'staled_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            if ($ids->isEmpty() && $a5Ids->isEmpty()) {
                return;
            }

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A4_STALED_BY_A3_RERUN',
                'actor_id' => $actorId,
                'from_status' => 'a4_complete',
                'to_status' => 'stale',
                'context' => [
                    'current_a3_run_id' => (int) $currentA3->id,
                    'current_a3_version' => (int) $currentA3->version,
                    'a4_run_ids' => $ids->values()->all(),
                    'a5_run_ids' => $a5Ids->values()->all(),
                    'reason' => $reason,
                ],
                'created_at' => $now,
            ]);
        });
    }

    /**
     * A successfully completed A4 re-run becomes the single current A4 authority.
     * Older completed A4 runs remain immutable historical evidence but are no
     * longer current and therefore receive stale/superseded metadata.
     */
    public function supersedeEarlierA4ForNewA4(AllocationA4Run $currentA4, ?int $actorId = null): void
    {
        DB::connection('exam')->transaction(function () use ($currentA4, $actorId): void {
            $now = now();
            $reason = "A4 Phase-2 was re-run and current source is now v{$currentA4->version}. This earlier A4 result is historical/superseded.";

            $ids = AllocationA4Run::query()
                ->where('status', 'a4_complete')
                ->where('is_stale', false)
                ->whereKeyNot((int) $currentA4->id)
                ->pluck('id');

            if ($ids->isEmpty()) {
                return;
            }

            AllocationA4Run::query()->whereIn('id', $ids)->update([
                'is_stale' => true,
                'stale_reason' => $reason,
                'staled_at' => $now,
                'updated_at' => $now,
            ]);

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A4_SUPERSEDED_BY_A4_RERUN',
                'actor_id' => $actorId,
                'from_status' => 'a4_complete',
                'to_status' => 'stale',
                'context' => [
                    'current_a4_run_id' => (int) $currentA4->id,
                    'current_a4_version' => (int) $currentA4->version,
                    'superseded_a4_run_ids' => $ids->values()->all(),
                    'reason' => $reason,
                ],
                'created_at' => $now,
            ]);
        });
    }

    /** A new completed A4 supersedes A5 validations bound to older A4 evidence. */
    public function staleA5ForNewA4(AllocationA4Run $currentA4, ?int $actorId = null): void
    {
        DB::connection('exam')->transaction(function () use ($currentA4, $actorId): void {
            $now = now();
            $reason = "A4 Phase-2 was re-run and current source is now v{$currentA4->version}. Re-run A5 Final Allocation Validity Check.";
            $ids = AllocationA5Run::query()
                ->whereIn('status', ['validated_ok','validated_failed','finalized'])
                ->where('is_stale', false)
                ->where('allocation_a4_run_id', '<>', (int) $currentA4->id)
                ->pluck('id');
            if ($ids->isEmpty()) return;

            AllocationA5Run::query()->whereIn('id', $ids)->update([
                'is_stale' => true, 'stale_reason' => $reason, 'staled_at' => $now, 'updated_at' => $now,
            ]);
            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A5_STALED_BY_A4_RERUN', 'actor_id' => $actorId,
                'from_status' => null, 'to_status' => 'stale',
                'context' => ['current_a4_run_id' => (int) $currentA4->id, 'current_a4_version' => (int) $currentA4->version, 'a5_run_ids' => $ids->values()->all(), 'reason' => $reason],
                'created_at' => $now,
            ]);
        });
    }

    /**
     * A successfully completed A5 re-run becomes the single current A5 authority.
     * Older A5 reports are retained as immutable historical evidence.
     */
    public function supersedeEarlierA5ForNewA5(AllocationA5Run $currentA5, ?int $actorId = null): void
    {
        DB::connection('exam')->transaction(function () use ($currentA5, $actorId): void {
            $now = now();
            $reason = "A5 Final Allocation Validity Check was re-run and current source is now v{$currentA5->version}. This earlier A5 result is historical/superseded.";

            $ids = AllocationA5Run::query()
                ->whereIn('status', ['validated_ok','validated_failed','finalized'])
                ->where('is_stale', false)
                ->whereKeyNot((int) $currentA5->id)
                ->pluck('id');

            if ($ids->isEmpty()) {
                return;
            }

            AllocationA5Run::query()->whereIn('id', $ids)->update([
                'is_stale' => true,
                'stale_reason' => $reason,
                'staled_at' => $now,
                'updated_at' => $now,
            ]);

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A5_SUPERSEDED_BY_A5_RERUN',
                'actor_id' => $actorId,
                'from_status' => null,
                'to_status' => 'stale',
                'context' => [
                    'current_a5_run_id' => (int) $currentA5->id,
                    'current_a5_version' => (int) $currentA5->version,
                    'superseded_a5_run_ids' => $ids->values()->all(),
                    'reason' => $reason,
                ],
                'created_at' => $now,
            ]);
        });
    }

    /**
     * Repairs only the historical false-positive produced by the old
     * reconcileCurrentness()->staleA4ForNewA3() behavior.
     *
     * A stale A5 is revived only when it is the latest completed A5, is bound to
     * the exact current A4, its A4 hash still matches, and its stale reason is the
     * known erroneous "A3 ... was re-run" reason. Genuine stale lineage is never
     * revived.
     */
    public function repairFalsePositiveA5ForCurrentA4(AllocationA4Run $currentA4): void
    {
        DB::connection('exam')->transaction(function () use ($currentA4): void {
            $latest = AllocationA5Run::query()
                ->whereIn('status', ['validated_ok','validated_failed','finalized'])
                ->latest('version')
                ->lockForUpdate()
                ->first();

            if (! $latest
                || (int) $latest->allocation_a4_run_id !== (int) $currentA4->id
                || ! $currentA4->a4_output_hash
                || ! $latest->a4_output_hash
                || ! hash_equals((string) $latest->a4_output_hash, (string) $currentA4->a4_output_hash)) {
                return;
            }

            $falsePositivePrefix = 'A3 Phase-1 was re-run and current source is now v';
            if ((bool) $latest->is_stale
                && str_starts_with((string) $latest->stale_reason, $falsePositivePrefix)) {
                $latest->forceFill([
                    'is_stale' => false,
                    'stale_reason' => null,
                    'staled_at' => null,
                ])->save();

                AllocationProcessingAudit::query()->create([
                    'event' => 'ALLOCATION_A5_FALSE_STALE_REPAIRED',
                    'actor_id' => null,
                    'from_status' => 'stale',
                    'to_status' => (string) $latest->status,
                    'context' => [
                        'allocation_a5_run_id' => (int) $latest->id,
                        'allocation_a5_version' => (int) $latest->version,
                        'allocation_a4_run_id' => (int) $currentA4->id,
                        'reason' => 'Repaired historical false-positive A5 staleness; exact current A4 lineage/hash still matches.',
                    ],
                    'created_at' => now(),
                ]);
            }

            // Any earlier A5 that is also bound to this exact current A4 and was
            // falsely staled by the same historical bug is historical, not current.
            $supersededReason = "A5 Final Allocation Validity Check was re-run and current source is now v{$latest->version}. This earlier A5 result is historical/superseded.";
            AllocationA5Run::query()
                ->whereIn('status', ['validated_ok','validated_failed','finalized'])
                ->where('allocation_a4_run_id', (int) $currentA4->id)
                ->where('version', '<', (int) $latest->version)
                ->where(function ($query) use ($falsePositivePrefix): void {
                    $query->where('is_stale', false)
                        ->orWhere('stale_reason', 'like', $falsePositivePrefix.'%');
                })
                ->update([
                    'is_stale' => true,
                    'stale_reason' => $supersededReason,
                    'staled_at' => now(),
                    'updated_at' => now(),
                ]);
        });
    }

    /**
     * Cheap defensive reconciliation for the Allocation landing page.
     * This catches config/manual-state drift even if an explicit mutation path
     * failed to propagate staleness.
     */
    public function reconcileCurrentness(): void
    {
        $state = AllocationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        $freezeId = (int) data_get($state->source_snapshot, 'input_freeze_id', 0);
        $freeze = $freezeId > 0 ? AllocationInputFreeze::query()->find($freezeId) : null;

        $settingHash = null;
        try {
            $settingHash = $this->settings->currentConfigHash();
        } catch (\Throwable) {
            // Readiness service will show the configuration error; do not hide it here.
        }

        $seat = $state->finalized_seat_breakup_version_id
            ? AllocationSeatBreakupVersion::query()->find($state->finalized_seat_breakup_version_id)
            : null;

        $currentA3 = AllocationRun::query()
            ->where('status', 'phase1_complete')
            ->where('is_stale', false)
            ->latest('version')
            ->first();

        if ($currentA3) {
            $reason = null;

            if ((bool) $state->is_stale) {
                $reason = (string) ($state->stale_reason ?: 'Allocation input state is stale. Re-freeze A2 and re-run A3/A4.');
            } elseif (! $freeze || (string) $freeze->status !== 'frozen') {
                $reason = 'Current A2 frozen input is missing/superseded. Re-freeze A2 and re-run A3/A4.';
            } elseif ((int) $currentA3->input_freeze_id !== (int) $freeze->id
                || ! hash_equals((string) $currentA3->input_fingerprint, (string) $freeze->input_fingerprint)
                || ! hash_equals((string) $currentA3->queue_hash, (string) $freeze->queue_hash)) {
                $reason = 'A2 frozen input version/fingerprint changed after A3. Re-run A3 and A4.';
            } elseif ($settingHash !== null && ! hash_equals((string) $currentA3->settings_hash, $settingHash)) {
                $reason = 'A1 Allocation Settings changed after A3. Freeze settings/A2 again, then re-run A3 and A4.';
            } elseif (! $seat || ! $seat->dataset_hash || ! hash_equals((string) $currentA3->seat_breakup_hash, (string) $seat->dataset_hash)) {
                $reason = 'Finalized Seat Breakup changed after A3. Re-freeze A2, then re-run A3 and A4.';
            }

            if ($reason !== null) {
                $this->staleA3AndA4($reason);
                $currentA3 = null;
            }
        }

        $currentA3 ??= AllocationRun::query()
            ->where('status', 'phase1_complete')
            ->where('is_stale', false)
            ->latest('version')
            ->first();

        if ($currentA3) {
            // Exactly one completed A3 is authoritative. Older completed runs
            // are preserved as historical/superseded evidence.
            $this->supersedeEarlierA3ForNewA3($currentA3);
            $this->staleA4ForNewA3($currentA3);
        }

        $currentA4 = AllocationA4Run::query()
            ->where('status', 'a4_complete')
            ->where('is_stale', false)
            ->latest('version')
            ->first();
        if ($currentA4) {
            // Defensive repair for historical data created before A4 re-runs
            // enforced a single current authority.
            $this->supersedeEarlierA4ForNewA4($currentA4);
            $this->staleA5ForNewA4($currentA4);
            $this->repairFalsePositiveA5ForCurrentA4($currentA4);
        }

        // Repair historical metadata inconsistencies: A5 must never remain
        // current when its exact A4 authority is already stale.
        $staleA4Ids = AllocationA4Run::query()->where('is_stale', true)->pluck('id');
        if ($staleA4Ids->isNotEmpty()) {
            $reason = 'Upstream A3/A4 Allocation result is STALE / OUTDATED. Re-run A4 and A5 before publication.';
            AllocationA5Run::query()
                ->whereIn('allocation_a4_run_id', $staleA4Ids)
                ->whereIn('status', ['validated_ok','validated_failed','finalized'])
                ->where('is_stale', false)
                ->update([
                    'is_stale' => true,
                    'stale_reason' => $reason,
                    'staled_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        // If processing state says the direct frozen input is stale, the A2 row
        // itself must not continue to advertise status=frozen.
        if ((bool) $state->is_stale && $freeze && (string) $freeze->status === 'frozen') {
            $freeze->forceFill(['status' => 'stale'])->save();
        }
    }
}
