<?php

namespace App\Services\Allocation;

use App\Models\AllocationInputFreeze;
use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\ManualAllocationChoiceAdjustmentEvent;
use App\Models\Registration;
use App\Services\ChoiceOptimization\FinalAllocationReadyChoiceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManualAllocationChoiceAdjustmentService
{
    public function __construct(
        private readonly FinalAllocationReadyChoiceService $finalChoices,
        private readonly AllocationRunStaleService $runStale,
    ) {}

    public function exclude(int $registrationId, int $choiceCode, string $reason, ?int $actorId): void
    {
        $this->record($registrationId, $choiceCode, 'EXCLUDE', $reason, $actorId);
    }

    public function restore(int $registrationId, int $choiceCode, string $reason, ?int $actorId): void
    {
        $this->record($registrationId, $choiceCode, 'RESTORE', $reason, $actorId);
    }

    private function record(int $registrationId, int $choiceCode, string $action, string $reason, ?int $actorId): void
    {
        $reason = trim($reason);
        if (mb_strlen($reason) < 3) throw ValidationException::withMessages(['reason'=>'A mandatory operator reason of at least 3 characters is required.']);

        $choice = ChoiceOptimizationHistoricalChoice::query()->where('registration_id', $registrationId)->first();
        if (! $choice) throw ValidationException::withMessages(['choice'=>'Finalized Allocation-ready Choice could not be resolved for this candidate.']);
        $baseCodes = array_values(array_map('intval', (array) $choice->final_choice_codes));
        if (! in_array($choiceCode, $baseCodes, true)) throw ValidationException::withMessages(['choice'=>'Only an existing Allocation Ready Choice may be adjusted. Choices cannot be added or reordered here.']);

        $currentlyExcluded = $this->finalChoices->isExcluded($registrationId, $choiceCode);
        if ($action === 'EXCLUDE' && $currentlyExcluded) throw ValidationException::withMessages(['choice'=>'This choice is already excluded.']);
        if ($action === 'RESTORE' && ! $currentlyExcluded) throw ValidationException::withMessages(['choice'=>'This choice is not currently excluded.']);

        $registration = Registration::query()->findOrFail($registrationId);
        DB::connection('exam')->transaction(function () use ($registrationId, $registration, $choiceCode, $action, $reason, $actorId): void {
            ManualAllocationChoiceAdjustmentEvent::query()->create([
                'registration_id'=>$registrationId,
                'reg'=>(string) $registration->reg,
                'choice_code'=>$choiceCode,
                'action'=>$action,
                'reason'=>$reason,
                'actor_id'=>$actorId,
                'created_at'=>now(),
            ]);

            $state = AllocationProcessingState::query()->whereKey(1)->lockForUpdate()->first();
            $freezeId = (int) data_get($state?->source_snapshot, 'input_freeze_id', 0);
            if ($freezeId > 0) AllocationInputFreeze::query()->whereKey($freezeId)->where('status','frozen')->update(['status'=>'stale','updated_at'=>now()]);
            if ($state) {
                $state->forceFill([
                    'status'=>'stale',
                    'is_stale'=>true,
                    'stale_reason'=>'Final Allocation Ready Choice changed by audited Manual Adjustment. Re-freeze A2 and re-run Allocation.',
                    'phase'=>'STALE',
                    'progress_message'=>'Manual Adjustment of Allocation Ready Choice changed the Allocation input authority.',
                ])->save();
            }

            AllocationProcessingAudit::query()->create([
                'event'=>'MANUAL_ALLOCATION_READY_CHOICE_'.$action,
                'actor_id'=>$actorId,
                'from_status'=>null,
                'to_status'=>'stale',
                'context'=>[
                    'registration_id'=>$registrationId,
                    'reg'=>(string) $registration->reg,
                    'choice_code'=>$choiceCode,
                    'action'=>$action,
                    'reason'=>$reason,
                    'seat_breakup_staled'=>false,
                ],
                'created_at'=>now(),
            ]);
        });

        $this->runStale->staleA3AndA4(
            'Final Allocation Ready Choice changed by audited Manual Adjustment. Re-freeze A2 and re-run/re-finalize Allocation. Seat Breakup remains current.',
            $actorId
        );
    }
}
