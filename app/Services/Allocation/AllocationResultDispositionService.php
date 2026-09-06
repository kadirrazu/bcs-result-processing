<?php

namespace App\Services\Allocation;

use App\Models\AllocationA5CandidateResult;
use App\Models\AllocationA5Run;
use App\Models\AllocationResultDisposition;
use App\Models\AllocationResultDispositionAudit;
use App\Models\AllocationResultDispositionState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A5.5 publication disposition layer. This service never releases seats or mutates A3/A4/A5 evidence.
 * ACTIVE is implicit for every A5 allocated candidate unless an operator explicitly changes it.
 */
final class AllocationResultDispositionService
{
    public const ACTIVE = 'ACTIVE';
    public const WITHHELD = 'WITHHELD';
    public const CANCELLED = 'CANCELLED';

    /** @return array{revision:int,hash:string,active:int,withheld:int,cancelled:int} */
    public function snapshot(AllocationA5Run $a5): array
    {
        $state = $this->ensureState($a5);
        return [
            'revision' => (int) $state->revision,
            'hash' => (string) $state->disposition_hash,
            'active' => (int) $state->active_count,
            'withheld' => (int) $state->withheld_count,
            'cancelled' => (int) $state->cancelled_count,
        ];
    }

    public function ensureState(AllocationA5Run $a5): AllocationResultDispositionState
    {
        $state = AllocationResultDispositionState::query()->firstOrCreate(
            ['allocation_a5_run_id' => (int) $a5->id],
            ['revision' => 0]
        );

        if (! $state->disposition_hash) {
            $this->refreshState($a5, $state, null, false);
            $state->refresh();
        }

        return $state;
    }

    public function effectiveStatus(AllocationA5Run $a5, int $registrationId): string
    {
        return (string) (AllocationResultDisposition::query()
            ->where('allocation_a5_run_id', $a5->id)
            ->where('registration_id', $registrationId)
            ->value('status') ?: self::ACTIVE);
    }

    /** @return Collection<int,AllocationResultDisposition> */
    public function dispositionMap(AllocationA5Run $a5, Collection $registrationIds): Collection
    {
        return AllocationResultDisposition::query()
            ->where('allocation_a5_run_id', $a5->id)
            ->whereIn('registration_id', $registrationIds->map(fn ($v) => (int) $v)->unique()->values())
            ->get()->keyBy('registration_id');
    }

    public function updateStatus(
        AllocationA5Run $a5,
        int $registrationId,
        string $toStatus,
        string $reason,
        ?string $referenceNo,
        ?int $actorId,
    ): AllocationResultDisposition {
        $toStatus = strtoupper(trim($toStatus));
        if (! in_array($toStatus, [self::ACTIVE, self::WITHHELD, self::CANCELLED], true)) {
            throw ValidationException::withMessages(['status' => 'Unsupported allocation publication status.']);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required for every status change.']);
        }

        return DB::connection('exam')->transaction(function () use ($a5, $registrationId, $toStatus, $reason, $referenceNo, $actorId): AllocationResultDisposition {
            $candidate = AllocationA5CandidateResult::query()
                ->where('allocation_a5_run_id', $a5->id)
                ->where('registration_id', $registrationId)
                ->lockForUpdate()->first();
            if (! $candidate) {
                throw ValidationException::withMessages(['candidate' => 'Candidate is not part of this finalized A5 Allocation result.']);
            }

            $current = AllocationResultDisposition::query()
                ->where('allocation_a5_run_id', $a5->id)
                ->where('registration_id', $registrationId)
                ->lockForUpdate()->first();
            $fromStatus = (string) ($current?->status ?: self::ACTIVE);
            if ($fromStatus === $toStatus) {
                throw ValidationException::withMessages(['status' => 'Candidate is already '.$toStatus.'.']);
            }

            $current = AllocationResultDisposition::query()->updateOrCreate(
                ['allocation_a5_run_id' => $a5->id, 'registration_id' => $registrationId],
                [
                    'reg' => (string) $candidate->reg,
                    'circular_entry_id' => (int) $candidate->circular_entry_id,
                    'cadre_code' => (int) $candidate->cadre_code,
                    'status' => $toStatus,
                    'reason' => $reason,
                    'reference_no' => filled($referenceNo) ? trim((string) $referenceNo) : null,
                    'changed_by' => $actorId,
                    'changed_at' => now(),
                ]
            );

            AllocationResultDispositionAudit::query()->create([
                'allocation_a5_run_id' => $a5->id,
                'registration_id' => $registrationId,
                'reg' => (string) $candidate->reg,
                'cadre_code' => (int) $candidate->cadre_code,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
                'reference_no' => filled($referenceNo) ? trim((string) $referenceNo) : null,
                'actor_id' => $actorId,
                'created_at' => now(),
            ]);

            $state = AllocationResultDispositionState::query()
                ->where('allocation_a5_run_id', $a5->id)->lockForUpdate()->first()
                ?: AllocationResultDispositionState::query()->create(['allocation_a5_run_id'=>$a5->id,'revision'=>0]);
            $this->refreshState($a5, $state, $actorId, true);

            return $current;
        });
    }

    /** Apply public publication rule: only ACTIVE allocated candidates may be published. */
    public function applyPublishedOnly(Builder $query, AllocationA5Run $a5, string $registrationColumn = 'registration_id'): Builder
    {
        return $query->whereNotExists(function ($sub) use ($a5, $registrationColumn): void {
            $sub->selectRaw('1')
                ->from('allocation_result_dispositions as ard')
                ->whereColumn('ard.registration_id', $registrationColumn)
                ->where('ard.allocation_a5_run_id', $a5->id)
                ->whereIn('ard.status', [self::WITHHELD, self::CANCELLED]);
        });
    }

    private function refreshState(AllocationA5Run $a5, AllocationResultDispositionState $state, ?int $actorId, bool $increment): void
    {
        $rows = AllocationResultDisposition::query()
            ->where('allocation_a5_run_id', $a5->id)
            ->orderBy('registration_id')
            ->get(['registration_id','status','reason','reference_no','changed_at']);

        $withheld = $rows->where('status', self::WITHHELD)->count();
        $cancelled = $rows->where('status', self::CANCELLED)->count();
        $total = (int) $a5->total_allocated;
        $active = max(0, $total - $withheld - $cancelled);
        $revision = (int) $state->revision + ($increment ? 1 : 0);
        $canonical = [
            'a5' => (int) $a5->id,
            'revision' => $revision,
            'rows' => $rows->map(fn ($row) => [
                (int) $row->registration_id,
                (string) $row->status,
                (string) ($row->reason ?? ''),
                (string) ($row->reference_no ?? ''),
                optional($row->changed_at)->format('Y-m-d H:i:s.u'),
            ])->values()->all(),
        ];

        $state->forceFill([
            'revision' => $revision,
            'disposition_hash' => hash('sha256', json_encode($canonical, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)),
            'active_count' => $active,
            'withheld_count' => $withheld,
            'cancelled_count' => $cancelled,
            'changed_by' => $actorId,
            'changed_at' => $increment ? now() : $state->changed_at,
        ])->save();
    }
}
