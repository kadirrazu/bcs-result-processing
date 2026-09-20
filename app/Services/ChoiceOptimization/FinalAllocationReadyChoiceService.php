<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\ManualAllocationChoiceAdjustmentEvent;
use Illuminate\Support\Collection;

/**
 * Optional non-destructive layer over finalized Allocation-ready Choice.
 * With no active EXCLUDE event, output is exactly the finalized Choice Optimization output.
 */
final class FinalAllocationReadyChoiceService
{
    /** @return array<int,array<int,string>> */
    public function baseMap(): array
    {
        return ChoiceOptimizationHistoricalChoice::query()
            ->orderBy('registration_id')
            ->get(['registration_id', 'final_choice_codes'])
            ->mapWithKeys(fn ($row): array => [
                (int) $row->registration_id => array_values(array_map('strval', (array) $row->final_choice_codes)),
            ])->all();
    }

    /** @return array<int,array<int,string>> */
    public function effectiveMap(): array
    {
        $base = $this->baseMap();
        foreach ($this->activeExclusions() as $registrationId => $codes) {
            if (! isset($base[$registrationId])) continue;
            $excluded = array_fill_keys(
                $codes->map(static fn ($code): string => (string) $code)->all(),
                true
            );
            $base[$registrationId] = array_values(array_filter(
                $base[$registrationId],
                static fn ($code): bool => ! isset($excluded[(string) $code])
            ));
        }
        return $base;
    }

    /** @return Collection<int,Collection<int,int>> registration_id => choice codes */
    public function activeExclusions(): Collection
    {
        $latest = [];
        foreach (ManualAllocationChoiceAdjustmentEvent::query()->orderBy('id')->cursor() as $event) {
            $key = (int) $event->registration_id.':'.(int) $event->choice_code;
            $latest[$key] = $event;
        }

        return collect($latest)
            ->filter(fn ($event) => strtoupper((string) $event->action) === 'EXCLUDE')
            ->groupBy(fn ($event) => (int) $event->registration_id)
            ->map(fn ($events) => $events->pluck('choice_code')->map(fn ($v) => (int) $v)->values());
    }

    /**
     * Latest effective manual exclusion events, grouped by candidate.
     * RESTORE removes the corresponding choice from this effective set.
     *
     * @param Collection<int,int>|null $registrationIds
     * @return Collection<int,Collection<int,ManualAllocationChoiceAdjustmentEvent>>
     */
    public function activeExclusionEvents(?Collection $registrationIds = null): Collection
    {
        $query = ManualAllocationChoiceAdjustmentEvent::query()->orderBy('id');

        if ($registrationIds !== null) {
            $ids = $registrationIds->map(fn ($id) => (int) $id)->filter()->unique()->values();
            if ($ids->isEmpty()) return collect();
            $query->whereIn('registration_id', $ids);
        }

        $latest = [];
        foreach ($query->get() as $event) {
            $key = (int) $event->registration_id.':'.(int) $event->choice_code;
            $latest[$key] = $event;
        }

        return collect($latest)
            ->filter(fn ($event) => strtoupper((string) $event->action) === 'EXCLUDE')
            ->groupBy(fn ($event) => (int) $event->registration_id)
            ->map(fn ($events) => $events->values());
    }

    public function isExcluded(int $registrationId, int $choiceCode): bool
    {
        $latest = ManualAllocationChoiceAdjustmentEvent::query()
            ->where('registration_id', $registrationId)
            ->where('choice_code', $choiceCode)
            ->latest('id')->first();
        return $latest && strtoupper((string) $latest->action) === 'EXCLUDE';
    }

    public function adjustmentStateHash(): string
    {
        $rows = [];
        foreach ($this->activeExclusions()->sortKeys() as $registrationId => $codes) {
            foreach ($codes->sort()->values() as $code) $rows[] = [(int) $registrationId, (int) $code];
        }
        return hash('sha256', json_encode($rows, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function datasetHash(string $baseChoiceOptimizationHash): string
    {
        // Strict optionality/backward compatibility: when no effective manual
        // exclusion exists, Final Allocation Ready Choice IS the original
        // Allocation Ready Choice, including its authoritative dataset hash.
        if ($this->activeExclusions()->isEmpty()) return $baseChoiceOptimizationHash;

        return hash('sha256', $baseChoiceOptimizationHash.'|manual-adjustment|'.$this->adjustmentStateHash());
    }

    /** @return array{total_candidates:int,adjusted_candidates:int,excluded_choices:int,unchanged_candidates:int} */
    public function adjustmentSummary(): array
    {
        $totalCandidates = ChoiceOptimizationHistoricalChoice::query()->count();
        $active = $this->activeExclusions();
        $adjustedCandidates = $active->count();
        $excludedChoices = $active->sum(fn (Collection $codes): int => $codes->count());

        return [
            'total_candidates' => $totalCandidates,
            'adjusted_candidates' => $adjustedCandidates,
            'excluded_choices' => $excludedChoices,
            'unchanged_candidates' => max(0, $totalCandidates - $adjustedCandidates),
        ];
    }

    public function hasActiveAdjustments(): bool
    {
        return $this->activeExclusions()->isNotEmpty();
    }
}
