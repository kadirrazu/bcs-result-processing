<?php

namespace App\Services\Allocation;

use App\Models\AllocationA4Result;
use App\Models\AllocationResultDisposition;
use App\Models\AllocationA5Run;
use App\Models\BachelorSubject;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\ChoiceOptimizationEffectiveChoice;
use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\ChoiceOptimizationOmrStaging;
use App\Models\ChoiceSourceItem;
use App\Models\ChoiceValidationResult;
use App\Models\CircularEntry;
use App\Models\District;
use App\Models\Gender;
use App\Models\MeritProcessingState;
use App\Models\MeritResult;
use App\Models\PostRelatedSubject;
use App\Models\PreliminaryResult;
use App\Models\Registration;
use App\Models\TabulationProcessingState;
use App\Models\TabulationResult;
use App\Models\VivaResult;
use App\Models\WrittenResult;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Read-only consolidated reporting queries bound to the current finalized A5. */
final class AllocationA6ReportService
{
    public function currentTabulationRunId(): ?int
    {
        $state = TabulationProcessingState::query()->find(1);
        return $state?->status === 'finalized' && ! (bool) $state?->is_stale
            ? (int) ($state->latest_run_id ?: 0) ?: null : null;
    }

    public function currentMeritRunId(): ?int
    {
        $state = MeritProcessingState::query()->find(1);
        return $state?->status === 'finalized' && ! (bool) $state?->is_stale
            ? (int) ($state->latest_run_id ?: 0) ?: null : null;
    }

    public function tabulationEligibleQuery(): Builder
    {
        $runId = $this->currentTabulationRunId();
        abort_if($runId === null, 409, 'Current finalized Tabulation source is unavailable.');

        return TabulationResult::query()
            ->where('processing_run_id', $runId)
            ->where(fn (Builder $q) => $q->where('general_merit_eligible', true)->orWhere('technical_merit_eligible', true));
    }

    /** @return Collection<int,array<string,mixed>> */
    public function cadres(AllocationA5Run $a5): Collection
    {
        $capacities = $a5->capacityResults()->get();
        $entries = CircularEntry::query()->whereIn('id', $capacities->pluck('circular_entry_id'))->get()->keyBy('id');
        $abbr = $this->abbreviations($capacities->pluck('cadre_code'));
        $dispositionCounts = AllocationResultDisposition::query()
            ->where('allocation_a5_run_id', (int) $a5->id)
            ->whereIn('status', ['WITHHELD','CANCELLED'])
            ->selectRaw('cadre_code, status, COUNT(*) as aggregate')
            ->groupBy('cadre_code','status')->get()
            ->groupBy('cadre_code');

        return $capacities->map(function ($capacity) use ($entries, $abbr, $dispositionCounts): array {
            $entry = $entries->get((int) $capacity->circular_entry_id);
            return [
                'capacity' => $capacity,
                'entry' => $entry,
                'code' => (int) $capacity->cadre_code,
                'abbr' => (string) $abbr->get((int) $capacity->cadre_code, '—'),
                'allocated' => (int) $capacity->allocated_count,
                'withheld' => (int) optional($dispositionCounts->get((int) $capacity->cadre_code, collect())->firstWhere('status','WITHHELD'))->aggregate,
                'cancelled' => (int) optional($dispositionCounts->get((int) $capacity->cadre_code, collect())->firstWhere('status','CANCELLED'))->aggregate,
                'published' => max(0, (int) $capacity->allocated_count
                    - (int) optional($dispositionCounts->get((int) $capacity->cadre_code, collect())->firstWhere('status','WITHHELD'))->aggregate
                    - (int) optional($dispositionCounts->get((int) $capacity->cadre_code, collect())->firstWhere('status','CANCELLED'))->aggregate),
                'group_rank' => $this->groupRank((string) ($entry?->cadre_type?->value ?? $entry?->cadre_type ?? '')),
                'serial' => (int) ($entry?->cadre_serial ?? PHP_INT_MAX),
                'sub_serial' => $entry?->sub_serial === null ? -1 : (int) $entry->sub_serial,
            ];
        })->sortBy(fn (array $r) => sprintf('%02d-%08d-%08d-%08d', $r['group_rank'], $r['serial'], $r['sub_serial'] + 1, $r['code']))->values();
    }

    /** @return array<string,mixed> */
    public function candidateDetail(string $reg, AllocationA5Run $a5): array
    {
        $registration = Registration::query()->where('reg', $reg)->firstOrFail();
        $tabRunId = $this->currentTabulationRunId();
        $meritRunId = $this->currentMeritRunId();
        $a4 = AllocationA4Result::query()->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)->where('reg', $reg)->first();
        $a5Result = $a5->candidateResults()->where('reg', $reg)->first();
        $choiceValidation = ChoiceValidationResult::query()->where('registration_id', $registration->id)->latest('validation_version')->first();
        $historicalChoice = ChoiceOptimizationHistoricalChoice::query()->where('registration_id', $registration->id)->latest('id')->first();
        $effectiveChoice = ChoiceOptimizationEffectiveChoice::query()->where('registration_id', $registration->id)->first();
        $omr = $effectiveChoice?->omr_staging_id
            ? ChoiceOptimizationOmrStaging::query()->find((int) $effectiveChoice->omr_staging_id)
            : null;

        // Registration Choice is immutable raw source evidence, not a reconstructed/validated list.
        $registrationChoices = $choiceValidation
            ? ChoiceSourceItem::query()->where('choice_validation_source_id', (int) $choiceValidation->choice_source_id)
                ->orderBy('position')->pluck('choice_code')->filter(fn ($v) => filled($v))->values()->all()
            : [];
        $validatedChoices = array_values(array_filter((array) ($choiceValidation?->validated_choice_codes ?? []), fn ($v) => filled($v)));
        $omrChoices = array_values(array_filter((array) ($effectiveChoice?->omr_override_choice_codes ?? $omr?->validated_omr_choice_codes ?? []), fn ($v) => filled($v)));

        // The A6 "Effective Choice" lane means the final allocation-ready sequence after all enabled optimization stages.
        $effectiveChoices = array_values(array_filter((array) (
            $historicalChoice?->final_choice_codes
            ?? $effectiveChoice?->effective_choice_codes
            ?? $validatedChoices
        ), fn ($v) => filled($v)));

        $choiceCodes = collect($registrationChoices)
            ->merge($validatedChoices)->merge($omrChoices)->merge($effectiveChoices)
            ->map(fn ($v) => (int) $v)->filter()->unique()->values();

        $choiceSummary = collect();
        if (filled($effectiveChoice?->change_reason_text)) {
            $choiceSummary->push((string) $effectiveChoice->change_reason_text);
        }
        if (filled($omr?->decision_resolution_reason)) {
            $choiceSummary->push((string) $omr->decision_resolution_reason);
        } elseif (filled($omr?->resolution_reason)) {
            $choiceSummary->push((string) $omr->resolution_reason);
        }
        if ($historicalChoice?->matched_cutoff) {
            $cutoff = (array) $historicalChoice->matched_cutoff;
            $choiceSummary->push(sprintf(
                'Previous BCS cutoff applied at choice #%s (%s) due to BCS %s recommendation%s.',
                str_pad((string) ($cutoff['choice_position'] ?? '—'), 2, '0', STR_PAD_LEFT),
                (string) ($cutoff['choice_code'] ?? '—'),
                (string) ($cutoff['historical_bcs_number'] ?? '—'),
                filled($cutoff['historical_cadre'] ?? null) ? ' in '.(string) $cutoff['historical_cadre'] : ''
            ));
        }
        if (! empty($historicalChoice?->removed_choice_codes)) {
            $choiceSummary->push('Historical optimization removed: '.collect((array) $historicalChoice->removed_choice_codes)->implode(', ').'.');
        }
        foreach ((array) ($historicalChoice?->warnings ?? []) as $warning) {
            $text = is_array($warning) ? ($warning['message'] ?? $warning['reason_message'] ?? null) : $warning;
            if (filled($text)) $choiceSummary->push((string) $text);
        }

        $allocationAbbr = $a4 ? (string) $this->abbreviations(collect([(int) $a4->cadre_code]))->get((int) $a4->cadre_code, '—') : null;
        $disposition = AllocationResultDisposition::query()
            ->where('allocation_a5_run_id', (int) $a5->id)
            ->where('registration_id', (int) $registration->id)->first();

        return [
            'registration' => $registration,
            'registration_reference' => [
                'sex' => $this->referenceLabel(Gender::query()->where('code', $registration->sex_code)->value('name'), $registration->sex_code),
                'district' => $this->referenceLabel(District::query()->where('code', $registration->district_code)->value('name'), $registration->district_code),
                'bachelor' => $this->referenceLabel(BachelorSubject::query()->where('subject_code', $registration->bachelor_subject_code)->value('subject_name'), $registration->bachelor_subject_code),
                'prs' => $this->referenceLabel(PostRelatedSubject::query()->where('subject_code', $registration->post_related_subject_code)->value('subject_name'), $registration->post_related_subject_code),
            ],
            'preliminary' => PreliminaryResult::query()->where('registration_id', $registration->id)->latest('id')->first(),
            'written' => WrittenResult::query()->where('registration_id', $registration->id)->latest('id')->first(),
            'viva' => VivaResult::query()->where('registration_id', $registration->id)->latest('id')->first(),
            'tabulation' => $tabRunId ? TabulationResult::query()->where('processing_run_id', $tabRunId)->where('registration_id', $registration->id)->first() : null,
            'choice_validation' => $choiceValidation,
            'choice_optimization' => $historicalChoice,
            'choice_reporting' => [
                'registration' => $registrationChoices,
                'validated' => $validatedChoices,
                'omr' => $omrChoices,
                'effective' => $effectiveChoices,
                'abbr' => $this->abbreviations($choiceCodes),
                'summary' => $choiceSummary->filter()->unique()->values(),
            ],
            'merit' => $meritRunId ? MeritResult::query()->where('processing_run_id', $meritRunId)->where('registration_id', $registration->id)->first() : null,
            'allocation' => $a4,
            'allocation_abbr' => $allocationAbbr,
            'allocation_status' => (string) ($disposition?->status ?: ($a4 ? 'ACTIVE' : '')),
            'disposition' => $disposition,
            'a5' => $a5Result,
        ];
    }

    public function abbreviations(Collection $codes): Collection
    {
        $codes = $codes->map(fn ($v) => (int) $v)->unique()->values();
        $cadres = CadreMaster::query()->whereIn('cadre_code', $codes)->pluck('cadre_abbr', 'cadre_code')
            ->mapWithKeys(fn ($abbr, $code) => [(int) $code => (string) $abbr]);
        $subs = CadreSubMaster::query()->whereIn('sub_cadre_code', $codes)->pluck('sub_cadre_abbr', 'sub_cadre_code')
            ->mapWithKeys(fn ($abbr, $code) => [(int) $code => (string) $abbr]);
        return $cadres->union($subs);
    }

    private function referenceLabel(mixed $name, mixed $code): string
    {
        if (! filled($code)) return '—';
        return (string) $code.' - '.(filled($name) ? (string) $name : 'UNMAPPED');
    }

    private function groupRank(string $type): int
    {
        return match (strtoupper($type)) { 'GG' => 0, 'TT' => 1, default => 2 };
    }
}
