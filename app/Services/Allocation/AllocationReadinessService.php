<?php

namespace App\Services\Allocation;

use App\Enums\PreliminaryProcessingStatus;
use App\Enums\WrittenProcessingStatus;
use App\Enums\VivaProcessingStatus;
use App\Models\AllocationProcessingState;
use App\Models\AllocationSeatBreakupVersion;
use App\Models\ChoiceOptimizationProcessingState;
use App\Models\ChoiceOptimizationSetting;
use App\Models\MeritFinalizationRun;
use App\Models\MeritProcessingState;
use App\Models\PreliminaryProcessingState;
use App\Models\Registration;
use App\Models\VivaProcessingState;
use App\Models\WrittenProcessingState;
use App\Services\ChoiceOptimization\ChoiceOptimizationHistoricalChoiceService;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use App\Services\Circular\CircularFinalizedDatasetService;
use App\Services\Merit\MeritFinalizedDatasetService;
use App\Services\Tabulation\TabulationFinalizedDatasetService;
use Throwable;

final class AllocationReadinessService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly ChoiceValidationFinalizedDatasetService $choices,
        private readonly TabulationFinalizedDatasetService $tabulation,
        private readonly MeritFinalizedDatasetService $merit,
        private readonly ChoiceOptimizationHistoricalChoiceService $optimized,
        private readonly AllocationSettingsService $settings,
        private readonly AllocationSeatBreakupService $seatBreakup,
        private readonly AllocationInputFreezeService $inputFreeze,
    ) {}

    /**
     * Lightweight landing-page inspection.
     *
     * IMPORTANT: this method intentionally does not re-hash the large finalized
     * upstream datasets. It resolves finalized/stale/version/hash metadata only.
     * Strict re-hashing remains a server-side pre-run/finalization responsibility.
     */
    public function inspectDashboard(): array
    {
        $checks = [];

        $hasRegistration = Registration::query()->exists();
        $checks['registration'] = $this->simple(
            'Registration',
            $hasRegistration,
            $hasRegistration ? 'Candidate dataset is present.' : 'Registration dataset is missing.'
        );

        $p = PreliminaryProcessingState::query()->first();
        $checks['preliminary'] = $this->simple(
            'Preliminary',
            $p?->status === PreliminaryProcessingStatus::ResultFinalized,
            $p?->status === PreliminaryProcessingStatus::ResultFinalized ? 'Result finalized.' : 'Preliminary result is not finalized.'
        );

        $w = WrittenProcessingState::query()->first();
        $checks['written'] = $this->simple(
            'Written',
            $w?->status === WrittenProcessingStatus::ResultFinalized && !(bool) $w?->is_stale,
            $w?->status === WrittenProcessingStatus::ResultFinalized && !(bool) $w?->is_stale ? 'Result finalized and current.' : 'Written result is not current/finalized.'
        );

        $v = VivaProcessingState::query()->first();
        $checks['viva'] = $this->simple(
            'Viva',
            $v?->status === VivaProcessingStatus::ResultFinalized && !(bool) $v?->is_stale,
            $v?->status === VivaProcessingStatus::ResultFinalized && !(bool) $v?->is_stale ? 'Result finalized and current.' : 'Viva result is not current/finalized.'
        );

        $this->stored($checks, 'circular', 'Circular', fn () => $this->circular->storedFinalizedSummary());
        $this->stored($checks, 'choice_validation', 'Choice Validation', fn () => $this->choices->storedFinalizedSummary());
        $this->stored($checks, 'tabulation', 'Tabulation', fn () => $this->tabulation->storedFinalizedSummary());
        $this->stored($checks, 'merit', 'Merit Generation', fn () => $this->storedMeritSummary());

        $coSetting = ChoiceOptimizationSetting::query()->first();
        if (! $coSetting || !(bool) $coSetting->optimization_enabled) {
            $checks['choice_optimization'] = [
                'ready' => true,
                'label' => 'Choice Optimization',
                'status' => 'BYPASSED',
                'hash_verified' => null,
                'stored_hash_present' => true,
                'detail' => 'Optimization is disabled; finalized Validated Choice is authoritative.',
            ];
        } else {
            $state = ChoiceOptimizationProcessingState::query()->first();
            $cvReady = (bool) ($checks['choice_validation']['ready'] ?? false);
            $cvHash = (string) ($checks['choice_validation']['dataset_hash'] ?? '');
            $coCvHash = (string) data_get($state?->source_snapshot, 'choice_validation_hash', '');
            $ready = $state
                && $cvReady
                && (string) $state->status === 'finalized'
                && !(bool) $state->is_stale
                && (string) ($state->dataset_hash ?? '') !== ''
                && $coCvHash !== ''
                && $cvHash !== ''
                && hash_equals($coCvHash, $cvHash);

            $checks['choice_optimization'] = [
                'ready' => (bool) $ready,
                'label' => 'Choice Optimization',
                'status' => $ready ? 'READY' : 'NOT_READY',
                'hash_verified' => null,
                'stored_hash_present' => $ready,
                'dataset_hash' => $ready ? (string) $state->dataset_hash : null,
                'detail' => $ready
                    ? 'Finalized Optimization is bound to the current finalized Choice Validation authority. Strict hash verification runs at the Allocation pre-run gate.'
                    : (! $cvReady
                        ? 'Choice Optimization depends on Choice Validation, which is currently stale/not finalized.'
                        : 'Choice Optimization is stale/not finalized, or its Choice Validation source snapshot no longer matches current authority.'),
            ];
        }

        $this->stored(
            $checks,
            'allocation_settings',
            'Allocation Settings',
            fn () => $this->settings->storedFinalizedSummary()
        );

        $this->stored($checks, 'seat_breakup', 'Seat Breakup', fn () => $this->storedSeatBreakupSummary());

        $upstreamReady = collect($checks)->every(fn ($c) => (bool) $c['ready']);
        $this->stored($checks, 'input_freeze', 'Allocation Input Freeze', fn () => $this->inputFreeze->storedCurrentSummary());

        return [
            'ready' => collect($checks)->every(fn ($c) => (bool) $c['ready']),
            'upstream_ready' => $upstreamReady,
            'checks' => $checks,
            'checked_at' => now(),
            'verification_mode' => 'stored_metadata',
        ];
    }

    /**
     * Strict integrity inspection for actual processing gates.
     * This intentionally performs expensive dataset hash verification.
     */
    public function inspectStrict(): array
    {
        $checks = [];
        $checks['registration'] = $this->simple('Registration', Registration::query()->exists(), Registration::query()->exists() ? 'Candidate dataset is present.' : 'Registration dataset is missing.');
        $p = PreliminaryProcessingState::query()->first();
        $checks['preliminary'] = $this->simple('Preliminary', $p?->status === PreliminaryProcessingStatus::ResultFinalized, $p?->status === PreliminaryProcessingStatus::ResultFinalized ? 'Result finalized.' : 'Preliminary result is not finalized.');
        $w = WrittenProcessingState::query()->first();
        $checks['written'] = $this->simple('Written', $w?->status === WrittenProcessingStatus::ResultFinalized && !(bool)$w?->is_stale, $w?->status === WrittenProcessingStatus::ResultFinalized && !(bool)$w?->is_stale ? 'Result finalized and current.' : 'Written result is not current/finalized.');
        $v = VivaProcessingState::query()->first();
        $checks['viva'] = $this->simple('Viva', $v?->status === VivaProcessingStatus::ResultFinalized && !(bool)$v?->is_stale, $v?->status === VivaProcessingStatus::ResultFinalized && !(bool)$v?->is_stale ? 'Result finalized and current.' : 'Viva result is not current/finalized.');

        $this->verified($checks, 'circular', 'Circular', fn()=> $this->circular->verifiedSummary());
        $this->verified($checks, 'choice_validation', 'Choice Validation', fn()=> $this->choices->verifiedSummary());
        $this->verified($checks, 'tabulation', 'Tabulation', fn()=> $this->tabulation->verifiedSummary());
        $this->verified($checks, 'merit', 'Merit Generation', fn()=> $this->merit->verifiedSummary());

        $coSetting = ChoiceOptimizationSetting::query()->first();
        if (!$coSetting || !(bool)$coSetting->optimization_enabled) {
            $checks['choice_optimization'] = ['ready'=>true,'label'=>'Choice Optimization','status'=>'BYPASSED','hash_verified'=>true,'detail'=>'Optimization is disabled; finalized Validated Choice is authoritative.'];
        } else {
            $state = ChoiceOptimizationProcessingState::query()->first();
            try {
                if (!$state || $state->status !== 'finalized' || $state->is_stale || !$state->dataset_hash) throw new \RuntimeException('Choice Optimization is not current/finalized.');
                if (! (bool) ($checks['choice_validation']['ready'] ?? false)) throw new \RuntimeException('Choice Optimization depends on current finalized Choice Validation.');
                $cvHash = (string) ($checks['choice_validation']['dataset_hash'] ?? '');
                $coCvHash = (string) data_get($state->source_snapshot, 'choice_validation_hash', '');
                if ($cvHash === '' || $coCvHash === '' || ! hash_equals($coCvHash, $cvHash)) throw new \RuntimeException('CHOICE_OPTIMIZATION_CHOICE_VALIDATION_SNAPSHOT_MISMATCH. Re-process/finalize Choice Optimization.');
                $actual = $this->optimized->outputHashFromDatabase();
                if (!hash_equals((string)$state->dataset_hash, $actual)) throw new \RuntimeException('CHOICE_OPTIMIZATION_HASH_MISMATCH.');
                $checks['choice_optimization'] = ['ready'=>true,'label'=>'Choice Optimization','status'=>'READY','hash_verified'=>true,'detail'=>'Finalized optimized choice hash verified.','dataset_hash'=>$actual];
            } catch (Throwable $e) { $checks['choice_optimization']=['ready'=>false,'label'=>'Choice Optimization','status'=>'NOT_READY','hash_verified'=>false,'detail'=>$e->getMessage()]; }
        }
        $this->verified($checks, 'allocation_settings', 'Allocation Settings', function(){ $s=$this->settings->verified(); return ['dataset_hash'=>$s->settings_hash]; });
        $this->verified($checks, 'seat_breakup', 'Seat Breakup', function(){ $s=$this->seatBreakup->verifiedFinalized(); return ['dataset_hash'=>$s->dataset_hash,'version'=>$s->version]; });

        $upstreamReady = collect($checks)->every(fn($c)=>(bool)$c['ready']);
        $this->verified($checks, 'input_freeze', 'Allocation Input Freeze', function () {
            $freeze = $this->inputFreeze->verifiedCurrent();
            return [
                'dataset_hash' => (string) $freeze->input_fingerprint,
                'queue_hash' => (string) $freeze->queue_hash,
                'version' => (int) $freeze->version,
            ];
        });

        return ['ready'=>collect($checks)->every(fn($c)=>(bool)$c['ready']), 'upstream_ready'=>$upstreamReady, 'checks'=>$checks, 'checked_at'=>now(), 'verification_mode'=>'strict'];
    }

    private function storedMeritSummary(): array
    {
        $state = MeritProcessingState::query()->first();
        if (! $state
            || (string) $state->status !== 'finalized'
            || (bool) $state->is_stale
            || ! $state->latest_finalization_run_id
        ) {
            throw new \RuntimeException('A current, non-stale finalized Merit dataset is required.');
        }

        $final = MeritFinalizationRun::query()->find($state->latest_finalization_run_id);
        if (! $final || ! $final->dataset_hash) {
            throw new \RuntimeException('Merit finalized hash metadata could not be resolved.');
        }

        return [
            'processing_run_id' => (int) $final->processing_run_id,
            'processing_version' => (int) $final->processing_version,
            'dataset_hash' => (string) $final->dataset_hash,
            'finalized_at' => $final->finalized_at,
        ];
    }

    private function storedSeatBreakupSummary(): array
    {
        $state = AllocationProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);
        $version = $state->finalized_seat_breakup_version_id
            ? AllocationSeatBreakupVersion::query()->find($state->finalized_seat_breakup_version_id)
            : null;

        if (! $version || (string) $version->status !== 'finalized' || ! $version->dataset_hash) {
            throw new \RuntimeException('A finalized/frozen Seat Breakup is required.');
        }

        $circular = $this->circular->storedFinalizedSummary();
        if ((int) $version->circular_version !== (int) $circular['version']
            || ! hash_equals((string) $version->circular_hash, (string) $circular['dataset_hash'])
        ) {
            throw new \RuntimeException('SEAT_BREAKUP_CIRCULAR_MISMATCH: Finalized Circular changed. Recreate and finalize Seat Breakup.');
        }

        return [
            'dataset_hash' => (string) $version->dataset_hash,
            'version' => (int) $version->version,
            'circular_version' => (int) $version->circular_version,
        ];
    }

    private function simple(string $label, bool $ready, string $detail): array
    {
        return [
            'ready' => $ready,
            'label' => $label,
            'status' => $ready ? 'READY' : 'NOT_READY',
            'hash_verified' => null,
            'stored_hash_present' => null,
            'detail' => $detail,
        ];
    }

    private function stored(array &$checks, string $key, string $label, callable $fn): void
    {
        try {
            $s = $fn();
            $checks[$key] = [
                'ready' => true,
                'label' => $label,
                'status' => 'READY',
                'hash_verified' => null,
                'stored_hash_present' => ! empty($s['dataset_hash']),
                'detail' => 'Finalized hash metadata is current. Strict hash verification runs at the Allocation pre-run gate.',
            ] + $s;
        } catch (Throwable $e) {
            $checks[$key] = [
                'ready' => false,
                'label' => $label,
                'status' => 'NOT_READY',
                'hash_verified' => false,
                'stored_hash_present' => false,
                'detail' => $e->getMessage(),
            ];
        }
    }

    private function verified(array &$checks,string $key,string $label,callable $fn): void
    {
        try {
            $s=$fn();
            $checks[$key]=['ready'=>true,'label'=>$label,'status'=>'READY','hash_verified'=>true,'detail'=>'Finalized/frozen integrity verified.']+$s;
        } catch(Throwable $e) {
            $checks[$key]=['ready'=>false,'label'=>$label,'status'=>'NOT_READY','hash_verified'=>false,'detail'=>$e->getMessage()];
        }
    }
}
