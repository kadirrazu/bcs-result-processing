<?php

namespace App\Services\Allocation;

use App\Models\AllocationA4Run;
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

            if ($a3Ids->isNotEmpty() || $a4Ids->isNotEmpty()) {
                AllocationProcessingAudit::query()->create([
                    'event' => 'ALLOCATION_PHASE_RESULTS_STALED',
                    'actor_id' => $actorId,
                    'from_status' => null,
                    'to_status' => 'stale',
                    'context' => [
                        'reason' => $reason,
                        'a3_run_ids' => $a3Ids->values()->all(),
                        'a4_run_ids' => $a4Ids->values()->all(),
                    ],
                    'created_at' => $now,
                ]);
            }
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
                'event' => 'ALLOCATION_A4_STALED_BY_A3_RERUN',
                'actor_id' => $actorId,
                'from_status' => 'a4_complete',
                'to_status' => 'stale',
                'context' => [
                    'current_a3_run_id' => (int) $currentA3->id,
                    'current_a3_version' => (int) $currentA3->version,
                    'a4_run_ids' => $ids->values()->all(),
                    'reason' => $reason,
                ],
                'created_at' => $now,
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
            $this->staleA4ForNewA3($currentA3);
        }
    }
}
