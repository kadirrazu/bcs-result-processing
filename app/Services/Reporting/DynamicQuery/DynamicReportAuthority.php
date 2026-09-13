<?php

namespace App\Services\Reporting\DynamicQuery;

use App\Enums\PreliminaryProcessingStatus;
use App\Enums\VivaProcessingStatus;
use App\Enums\WrittenProcessingStatus;
use App\Models\AllocationA5Run;
use App\Models\MeritProcessingState;
use App\Models\PreliminaryProcessingState;
use App\Models\TabulationProcessingState;
use App\Models\VivaProcessingState;
use App\Models\WrittenProcessingState;

final class DynamicReportAuthority
{
    /** @return array<string,mixed> */
    public function resolve(array $sources): array
    {
        $warnings = [];
        $authority = [
            'preliminary_finalization_run_id' => null,
            'written_processing_run_id' => null,
            'viva_processing_run_id' => null,
            'tabulation_run_id' => null,
            'merit_run_id' => null,
            'allocation_a5_run_id' => null,
            'warnings' => &$warnings,
        ];

        if (in_array('preliminary', $sources, true)) {
            $state = PreliminaryProcessingState::query()->find(1);
            $status = $state?->status instanceof PreliminaryProcessingStatus ? $state->status->value : (string) ($state?->status ?? '');
            if ($state && $status === PreliminaryProcessingStatus::ResultFinalized->value && $state->latest_finalization_run_id) {
                $authority['preliminary_finalization_run_id'] = (int) $state->latest_finalization_run_id;
            } else {
                $warnings[] = 'Current finalized Preliminary authority is unavailable; Preliminary fields cannot be queried.';
            }
        }

        if (in_array('written', $sources, true)) {
            $state = WrittenProcessingState::query()->find(1);
            $status = $state?->status instanceof WrittenProcessingStatus ? $state->status->value : (string) ($state?->status ?? '');
            if ($state && $status === WrittenProcessingStatus::ResultFinalized->value && ! $state->is_stale && $state->latest_processing_run_id) {
                $authority['written_processing_run_id'] = (int) $state->latest_processing_run_id;
            } else {
                $warnings[] = 'Current finalized Written authority is unavailable; Written fields cannot be queried.';
            }
        }

        if (in_array('viva', $sources, true)) {
            $state = VivaProcessingState::query()->find(1);
            $status = $state?->status instanceof VivaProcessingStatus ? $state->status->value : (string) ($state?->status ?? '');
            if ($state && $status === VivaProcessingStatus::ResultFinalized->value && ! $state->is_stale && $state->latest_processing_run_id) {
                $authority['viva_processing_run_id'] = (int) $state->latest_processing_run_id;
            } else {
                $warnings[] = 'Current finalized Viva authority is unavailable; Viva fields cannot be queried.';
            }
        }

        if (in_array('tabulation', $sources, true)) {
            $state = TabulationProcessingState::query()->find(1);
            if ($state && (string) $state->status === 'finalized' && ! $state->is_stale && $state->latest_run_id) {
                $authority['tabulation_run_id'] = (int) $state->latest_run_id;
            } else {
                $warnings[] = 'Current finalized Tabulation authority is unavailable; Tabulation fields cannot be queried.';
            }
        }

        if (in_array('merit', $sources, true)) {
            $state = MeritProcessingState::query()->find(1);
            if ($state && $state->status === 'finalized' && ! $state->is_stale && $state->latest_run_id) {
                $authority['merit_run_id'] = (int) $state->latest_run_id;
            } else {
                $warnings[] = 'Current finalized Merit authority is unavailable; Merit fields cannot be queried.';
            }
        }

        if (in_array('allocation', $sources, true)) {
            $a5 = AllocationA5Run::query()->where('status', 'finalized')->where('is_stale', false)->latest('version')->first();
            if ($a5) {
                $authority['allocation_a5_run_id'] = (int) $a5->id;
            } else {
                $warnings[] = 'Current finalized Allocation authority is unavailable; Allocation fields cannot be queried.';
            }
        }

        unset($authority['warnings']);
        $authority['warnings'] = $warnings;
        return $authority;
    }
}
