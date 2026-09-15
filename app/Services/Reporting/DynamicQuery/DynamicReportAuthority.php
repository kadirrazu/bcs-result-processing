<?php

namespace App\Services\Reporting\DynamicQuery;

use App\Enums\PreliminaryProcessingStatus;
use App\Enums\VivaProcessingStatus;
use App\Enums\WrittenProcessingStatus;
use App\Models\AllocationA5Run;
use App\Models\AllocationResultDispositionState;
use App\Models\ChoiceOptimizationProcessingState;
use App\Models\ChoiceValidationProcessingState;
use App\Models\CircularProcessingState;
use App\Models\MeritProcessingState;
use App\Models\PreliminaryProcessingState;
use App\Models\TabulationProcessingState;
use App\Models\VivaProcessingState;
use App\Models\WrittenProcessingState;

final class DynamicReportAuthority
{
    public function __construct(
        private readonly \App\Services\ChoiceOptimization\FinalAllocationReadyChoiceService $finalChoices,
        private readonly \App\Services\Allocation\AllocationA6ReadinessService $allocationReadiness,
    ) {}


    /**
     * Sources that are safe to expose in Step-1 of the Dynamic Query Builder.
     *
     * Browser discovery is intentionally fail-closed: a field is discoverable
     * only when the same authority contract used at query execution is current.
     * Registration is the candidate-centric base source and has no processing
     * state in this project. Circular fields describe the allocated post, so
     * they additionally require current Allocation authority.
     *
     * @return array<int,string>
     */
    public function browserReadySources(): array
    {
        $ready = ['registrations'];
        $requirements = [
            'preliminary' => 'preliminary_finalization_run_id',
            'written' => 'written_processing_run_id',
            'viva' => 'viva_processing_run_id',
            'tabulation' => 'tabulation_run_id',
            'merit' => 'merit_run_id',
            'choice_validation' => 'choice_validation_finalization_run_id',
            'choice_optimization' => 'choice_optimization_hash',
            'allocation' => 'allocation_a5_run_id',
            'allocation_disposition' => 'allocation_a5_run_id',
        ];

        // Resolve the complete browser authority snapshot once. This keeps
        // Step-1 cheap while reusing the exact same currentness checks as
        // preview/export execution, including the fail-closed Allocation gate.
        $authority = $this->resolve(array_keys($requirements));
        foreach ($requirements as $source => $key) {
            if (! empty($authority[$key])) {
                $ready[] = $source;
            }
        }

        // Candidate-centric Circular fields are joined through the allocated
        // post. Match DynamicQueryCompiler::normalizeSources(): Circular is
        // selectable only when both Circular and Allocation are current.
        $circular = $this->resolve(['circular']);
        if (! empty($circular['circular_version']) && ! empty($authority['allocation_a5_run_id'])) {
            $ready[] = 'circular';
        }

        return array_values(array_unique($ready));
    }

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
            'allocation_disposition_revision' => null,
            'allocation_disposition_hash' => null,
            'choice_validation_finalization_run_id' => null,
            'choice_validation_version' => null,
            'choice_optimization_hash' => null,
            'final_allocation_ready_choice_hash' => null,
            'circular_version' => null,
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


        if (in_array('choice_validation', $sources, true)) {
            $state = ChoiceValidationProcessingState::query()->find(1);
            if ($state && (string) $state->status === 'finalized' && ! $state->is_stale && $state->latest_finalization_run_id && $state->finalized_validation_version) {
                $authority['choice_validation_finalization_run_id'] = (int) $state->latest_finalization_run_id;
                $authority['choice_validation_version'] = (int) $state->finalized_validation_version;
            } else {
                $warnings[] = 'Current finalized Choice Validation authority is unavailable; Choice Validation fields cannot be queried.';
            }
        }

        if (in_array('choice_optimization', $sources, true)) {
            $state = ChoiceOptimizationProcessingState::query()->find(1);
            if ($state && (string) $state->status === 'finalized' && ! $state->is_stale && $state->dataset_hash) {
                $authority['choice_optimization_hash'] = (string) $state->dataset_hash;
                $authority['final_allocation_ready_choice_hash'] = $this->finalChoices->datasetHash((string) $state->dataset_hash);
            } else {
                $warnings[] = 'Current finalized Choice Optimization authority is unavailable; Choice Optimization fields cannot be queried.';
            }
        }

        if (in_array('circular', $sources, true)) {
            $state = CircularProcessingState::query()->find(1);
            $status = $state?->status instanceof \BackedEnum ? $state->status->value : (string) ($state?->status ?? '');
            if ($state && $status === 'finalized' && ! $state->is_stale && $state->finalized_version) {
                $authority['circular_version'] = (int) $state->finalized_version;
            } else {
                $warnings[] = 'Current finalized Circular authority is unavailable; Circular fields cannot be queried.';
            }
        }

        if (in_array('allocation', $sources, true) || in_array('allocation_disposition', $sources, true)) {
            // Allocation fields are publishable only through the same fail-closed
            // authority gate used by A6/Cadre Reporting. A locally non-stale A5 row
            // is not sufficient when Merit/A2/another direct prerequisite is NOT READY.
            $allocationGate = $this->allocationReadiness->inspect();
            $a5 = ($allocationGate['ready'] ?? false) ? ($allocationGate['a5'] ?? null) : null;
            if ($a5 instanceof AllocationA5Run) {
                $authority['allocation_a5_run_id'] = (int) $a5->id;

                if (in_array('allocation_disposition', $sources, true)) {
                    $disposition = AllocationResultDispositionState::query()
                        ->where('allocation_a5_run_id', (int) $a5->id)
                        ->first();

                    $authority['allocation_disposition_revision'] = (int) ($disposition?->revision ?? 0);
                    $authority['allocation_disposition_hash'] = (string) ($disposition?->disposition_hash ?? '');
                }
            } else {
                $warnings[] = 'Current finalized Allocation authority is unavailable; Allocation fields cannot be queried.';
            }
        }

        unset($authority['warnings']);
        $authority['warnings'] = $warnings;
        return $authority;
    }
}
