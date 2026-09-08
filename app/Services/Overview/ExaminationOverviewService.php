<?php

namespace App\Services\Overview;

use App\Models\AllocationProcessingState;
use App\Models\AllocationResultDispositionState;
use App\Models\VivaResult;
use App\Models\ChoiceOptimizationEffectiveChoice;
use App\Models\ChoiceOptimizationProcessingState;
use App\Models\ChoiceValidationProcessingState;
use App\Models\CircularProcessingState;
use App\Models\MeritProcessingState;
use App\Models\PreliminaryProcessingState;
use App\Models\Registration;
use App\Models\TabulationProcessingState;
use App\Models\VivaProcessingState;
use App\Models\WrittenProcessingState;
use App\Services\Allocation\AllocationA6ReadinessService;

/** Lightweight current-examination operational overview. */
final class ExaminationOverviewService
{
    public function __construct(private readonly AllocationA6ReadinessService $a6Readiness) {}

    /** @return array<string,mixed> */
    public function build(): array
    {
        $registration = Registration::query()
            ->selectRaw('COUNT(*) total')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) active")
            ->selectRaw("SUM(CASE WHEN status = 'withheld' THEN 1 ELSE 0 END) withheld")
            ->selectRaw("SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) cancelled")
            ->selectRaw("SUM(CASE WHEN has_quota = 1 THEN 1 ELSE 0 END) quota_total")
            ->selectRaw("SUM(CASE WHEN has_ff_quota = 2 THEN 1 ELSE 0 END) quota_cff")
            ->selectRaw("SUM(CASE WHEN has_em_quota = 1 THEN 1 ELSE 0 END) quota_em")
            ->selectRaw("SUM(CASE WHEN has_phc_quota = 1 THEN 1 ELSE 0 END) quota_phc")
            ->first();

        $preliminary = PreliminaryProcessingState::query()->find(1);
        $written = WrittenProcessingState::query()->find(1);
        $viva = VivaProcessingState::query()->find(1);
        $vivaFlags = VivaResult::query()
            ->selectRaw('SUM(CASE WHEN issue_flag = 1 THEN 1 ELSE 0 END) issue_count')
            ->selectRaw('SUM(CASE WHEN invalid_flag = 1 THEN 1 ELSE 0 END) invalid_count')
            ->first();
        $circular = CircularProcessingState::query()->find(1);
        $choice = ChoiceValidationProcessingState::query()->find(1);
        $tabulation = TabulationProcessingState::query()->find(1);
        $merit = MeritProcessingState::query()->find(1);
        $optimization = ChoiceOptimizationProcessingState::query()->find(1);
        $effectiveChoices = ChoiceOptimizationEffectiveChoice::query()
            ->selectRaw('COUNT(*) registration_choice_count')
            ->selectRaw("SUM(CASE WHEN choice_source = 'validated_choice' THEN 1 ELSE 0 END) registration_intact")
            ->selectRaw("SUM(CASE WHEN choice_source = 'viva_omr_override' THEN 1 ELSE 0 END) omr_overridden")
            ->first();
        $allocation = AllocationProcessingState::query()->find(1);
        $a6 = $this->a6Readiness->inspect();

        $modules = [
            $this->module('Registration', 'registrations.index', ($registration?->total ?? 0) > 0 ? 'data_available' : 'not_started', false, [
                'Total' => (int) ($registration?->total ?? 0),
                'Active' => (int) ($registration?->active ?? 0),
                'Withheld' => (int) ($registration?->withheld ?? 0),
                'Cancelled' => (int) ($registration?->cancelled ?? 0),
                'Quota Total' => (int) ($registration?->quota_total ?? 0),
                'CFF' => (int) ($registration?->quota_cff ?? 0),
                'EM' => (int) ($registration?->quota_em ?? 0),
                'PHC' => (int) ($registration?->quota_phc ?? 0),
            ]),
            $this->stateModule('Preliminary', 'preliminary.index', $preliminary, $this->preliminaryStats($preliminary)),
            $this->stateModule('Written', 'written.index', $written, $this->writtenStats($written)),
            $this->stateModule('Viva', 'viva.index', $viva, $this->vivaStats($viva, $vivaFlags)),
            $this->stateModule('Circular', 'circular.index', $circular, (array) ($circular?->summary ?? [])),
            $this->stateModule('Choice Validation', 'choice-validation.index', $choice, (array) ($choice?->summary ?? [])),
            $this->stateModule('Tabulation', 'tabulation.index', $tabulation, (array) ($tabulation?->summary ?? [])),
            $this->stateModule('Merit', 'merit.index', $merit, (array) ($merit?->summary ?? [])),
            $this->stateModule('Choice Optimization', 'choice-optimization.index', $optimization, $this->choiceOptimizationStats($optimization, $effectiveChoices)),
            $this->stateModule('Allocation', 'allocation.index', $allocation, $this->allocationStats($a6)),
        ];

        return [
            'modules' => $modules,
            'reporting' => $a6,
            'stale_count' => collect($modules)->where('stale', true)->count(),
            'not_started_count' => collect($modules)->where('status_key', 'not_started')->count(),
        ];
    }

    /** @param array<string,mixed> $stats */
    private function module(string $name, string $route, string $status, bool $stale, array $stats): array
    {
        $statusKey = $stale ? 'stale' : strtolower($status);
        return [
            'name' => $name,
            'route' => $route,
            'status_key' => $statusKey,
            'status' => $stale ? 'Stale / Outdated' : $this->humanize($status),
            'stale' => $stale,
            'tone' => $this->tone($statusKey),
            'stats' => $this->compactStats($stats),
        ];
    }

    /** @param object|null $state @param array<string,mixed> $stats */
    private function stateModule(string $name, string $route, ?object $state, array $stats): array
    {
        if ($state === null) {
            return $this->module($name, $route, 'not_started', false, $stats);
        }

        $status = $state->status ?? 'not_started';
        $raw = $status instanceof \BackedEnum ? (string) $status->value : (string) $status;
        return $this->module($name, $route, $raw ?: 'not_started', (bool) ($state->is_stale ?? false), $stats);
    }

    /** @param array<string,mixed>|null $summary @return array<string,mixed> */
    private function summary(?array $summary, string $preferred): array
    {
        $summary ??= [];
        return isset($summary[$preferred]) && is_array($summary[$preferred]) ? $summary[$preferred] : $summary;
    }

    /** @return array<string,int> */
    private function preliminaryStats(?PreliminaryProcessingState $state): array
    {
        $summary = $this->summary($state?->summary, 'finalization');
        $outcome = static fn (string $key): int => (int) data_get($summary, $key.'.total', 0);

        return [
            'Appeared' => $outcome('pass') + $outcome('fail') + $outcome('cancelled') + $outcome('withheld') + $outcome('expelled'),
            'Absent' => $outcome('absent'),
            'Passed' => $outcome('pass'),
        ];
    }

    /** @return array<string,int> */
    private function writtenStats(?WrittenProcessingState $state): array
    {
        $summary = $this->summary($state?->summary, 'finalization');

        return [
            'Total' => (int) ($summary['qualified_total'] ?? 0) + max(0, (int) ($summary['failed_total'] ?? 0) - (int) ($summary['completely_absent'] ?? 0)) + (int) ($summary['completely_absent'] ?? 0),
            'Qualified' => (int) ($summary['qualified_total'] ?? 0),
            'Failed' => max(0, (int) ($summary['failed_total'] ?? 0) - (int) ($summary['completely_absent'] ?? 0)),
            'Absent' => (int) ($summary['completely_absent'] ?? 0),
            'Pass %' => $this->percent((int) ($summary['qualified_total'] ?? 0), (int) ($summary['qualified_total'] ?? 0) + (int) ($summary['failed_total'] ?? 0)),
        ];
    }

    /** @param object|null $flags @return array<string,int|float> */
    private function vivaStats(?VivaProcessingState $state, ?object $flags): array
    {
        $summary = $this->summary($state?->summary, 'finalization');
        $total = (int) ($summary['total_records'] ?? 0);
        $pass = (int) ($summary['pass'] ?? 0);

        return [
            'Total' => $total,
            'Pass' => $pass,
            'Fail' => max(0, (int) ($summary['fail'] ?? 0) - (int) ($summary['absent'] ?? 0)),
            'Absent' => (int) ($summary['absent'] ?? 0),
            'Issue' => (int) ($flags?->issue_count ?? 0),
            'Invalid' => (int) ($flags?->invalid_count ?? 0),
            'Pass %' => $this->percent($pass, $total),
        ];
    }

    /** @param object|null $effectiveChoices @return array<string,int> */
    private function choiceOptimizationStats(?ChoiceOptimizationProcessingState $state, ?object $effectiveChoices): array
    {
        $historical = (array) data_get($state?->summary, 'historical_choice_optimization', []);

        return [
            'Registration Choice Count' => (int) ($effectiveChoices?->registration_choice_count ?? 0),
            'Registration Intact' => (int) ($effectiveChoices?->registration_intact ?? 0),
            'OMR Overridden' => (int) ($effectiveChoices?->omr_overridden ?? 0),
            'Optimized' => (int) ($historical['optimized_candidates'] ?? 0),
            'Unchanged' => (int) ($historical['unchanged_candidates'] ?? 0),
            'Choice Empty After Optimization' => (int) ($historical['no_higher_choice_candidates'] ?? 0),
        ];
    }

    /** @param array<string,mixed> $a6 @return array<string,int|string> */
    private function allocationStats(array $a6): array
    {
        $a5 = $a6['a5'] ?? null;
        $a4 = $a5?->a4Run;
        $remainingPost = $a5?->capacityResults()->sum('remaining_posts') ?? 0;
        $disposition = $a5 ? AllocationResultDispositionState::query()->where('allocation_a5_run_id', (int) $a5->id)->first() : null;
        $cff = (int) ($a4?->cff_count ?? 0);
        $em = (int) ($a4?->em_count ?? 0);
        $phc = (int) ($a4?->phc_count ?? 0);

        return [
            'Allocated' => (int) ($a4?->allocated_count ?? $a5?->total_allocated ?? 0),
            'Withheld' => (int) ($disposition?->withheld_count ?? 0),
            'Cancelled' => (int) ($disposition?->cancelled_count ?? 0),
            'Final Publishing Ready Allocated' => (int) ($disposition?->active_count ?? $a5?->total_allocated ?? 0),
            'Quota Allocation Total' => $cff + $em + $phc,
            'CFF' => $cff,
            'EM' => $em,
            'PHC' => $phc,
            'Remain Post' => (int) $remainingPost,
            'A5 Status' => $a5 ? $this->humanize((string) ($a5->status instanceof \BackedEnum ? $a5->status->value : $a5->status)) : 'Not Started',
            'A6 Ready' => (bool) ($a6['ready'] ?? false) ? 'Yes' : 'No',
        ];
    }

    private function percent(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round(($numerator / $denominator) * 100, 2) : 0.0;
    }

    /** @param array<string,mixed> $stats @return array<string,mixed> */
    private function compactStats(array $stats): array
    {
        $out = [];
        foreach ($stats as $key => $value) {
            if (is_array($value) || is_object($value) || $value === null || $value === '') continue;
            $label = is_string($key)
                ? ((str_contains($key, '_') || str_contains($key, '-')) ? $this->humanize($key) : $key)
                : (string) $key;
            $out[$label] = $value;
            if (count($out) >= 14) break;
        }
        return $out;
    }

    private function humanize(string $value): string
    {
        return ucwords(str_replace(['_', '-'], ' ', strtolower($value)));
    }

    private function tone(string $status): string
    {
        if ($status === 'stale' || str_contains($status, 'fail') || str_contains($status, 'blocked')) return 'danger';
        if (str_contains($status, 'final') || str_contains($status, 'complete') || str_contains($status, 'ready') || $status === 'data_available') return 'success';
        if ($status === 'not_started' || str_contains($status, 'draft')) return 'secondary';
        return 'warning';
    }
}
