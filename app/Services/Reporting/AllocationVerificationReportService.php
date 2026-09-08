<?php

namespace App\Services\Reporting;

use App\Enums\CadreCategory;
use App\Models\AllocationA4Result;
use App\Models\AllocationA4Run;
use App\Models\AllocationA4SeatLedger;
use App\Models\AllocationInputCandidate;
use App\Models\AllocationInputQueueEntry;
use App\Models\AllocationA5Run;
use App\Models\BachelorSubject;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\ChoiceOptimizationHistoricalChoice;
use App\Models\MeritCadreRank;
use App\Models\MeritResult;
use App\Models\PostRelatedSubject;
use App\Models\Registration;
use App\Services\Allocation\AllocationA6ReportService;
use Illuminate\Support\Collection;

/**
 * Identity-free, read-only Allocation Verification reporting.
 *
 * Important: this service never re-runs Allocation rules. It reads only the
 * current finalized Merit + A4/A5 publication authority and normalizes those
 * facts for manual pre-publication verification.
 */
final class AllocationVerificationReportService
{
    public function __construct(private readonly AllocationA6ReportService $a6) {}

    /** @return Collection<int,array<string,mixed>> */
    public function technicalCadres(AllocationA5Run $a5): Collection
    {
        $meritRunId = $this->requireMeritRunId();
        $eligible = MeritCadreRank::query()
            ->where('processing_run_id', $meritRunId)
            ->where('cadre_type', 'TT')
            ->selectRaw('cadre_code, COUNT(*) as eligible_count')
            ->groupBy('cadre_code')
            ->pluck('eligible_count', 'cadre_code');

        // Reuse A6's current finalized Circular/A5 ordering rather than inventing a report-only cadre order.
        return $this->a6->cadres($a5)
            ->filter(function (array $row): bool {
                $type = $row['entry']?->cadre_type;
                $type = $type instanceof \BackedEnum ? $type->value : $type;
                return strtoupper((string) $type) === 'TT';
            })
            ->map(fn (array $row) => [
                'code' => (int) $row['code'],
                'abbr' => (string) $row['abbr'],
                'eligible_count' => (int) ($eligible->get((int) $row['code']) ?? 0),
            ])->values();
    }

    /** @return array{title:string,cadre:?array,rows:Collection,summary:?array} */
    public function build(string $type, AllocationA5Run $a5, ?int $cadreCode = null): array
    {
        $meritRunId = $this->requireMeritRunId();
        $type = strtolower(trim($type));

        $base = MeritResult::query()
            ->where('merit_results.processing_run_id', $meritRunId)
            ->select('merit_results.*');

        $title = '';
        $cadre = null;
        $specificRanks = collect();

        if ($type === 'common') {
            $title = 'Common Merit Position Allocation Verification Report';
            $base->whereNotNull('common_merit_position')
                ->orderBy('common_merit_position');
        } elseif ($type === 'general') {
            $title = 'General Cadre Candidate Allocation Report';
            // General-side population is authoritative Merit eligibility (GG + GT in the current normalized model).
            $base->whereNotNull('general_merit_position')
                ->orderBy('general_merit_position');
        } elseif ($type === 'technical-only') {
            $title = 'Only Technical Cadre Candidate Allocation Report';
            // "Only Technical" means the normalized TT category, not GT candidates who also have a technical side.
            $base->where('cadre_category', CadreCategory::Technical->value)
                ->whereNotNull('technical_merit_position')
                ->orderBy('technical_merit_position');
        } elseif ($type === 'technical-cadre') {
            abort_if(! $cadreCode, 404);
            $rankRows = MeritCadreRank::query()
                ->where('processing_run_id', $meritRunId)
                ->where('cadre_type', 'TT')
                ->where('cadre_code', $cadreCode)
                ->orderBy('cadre_merit_position')
                ->get();
            abort_if($rankRows->isEmpty(), 404, 'No current technical cadre merit evidence was found.');

            $specificRanks = $rankRows->keyBy('registration_id');
            $base->whereIn('registration_id', $rankRows->pluck('registration_id'));

            $first = $rankRows->first();
            $cadre = [
                'code' => (int) $cadreCode,
                'abbr' => (string) $first->cadre_abbr,
                'name' => $this->cadreName((int) $cadreCode),
            ];
            $title = $cadre['abbr'].' Technical Cadre Allocation Verification Report';
        } else {
            abort(404);
        }

        $merits = $base->get();
        if ($type === 'technical-cadre') {
            $byRegistration = $merits->keyBy('registration_id');
            $merits = $specificRanks->sortBy('cadre_merit_position')
                ->map(fn ($rank) => $byRegistration->get((int) $rank->registration_id))
                ->filter()->values();
        }
        $registrationIds = $merits->pluck('registration_id')->map(fn ($v) => (int) $v)->values();

        $registrations = Registration::query()
            ->whereIn('id', $registrationIds)
            ->get(['id','bachelor_subject_code','post_related_subject_code'])
            ->keyBy('id');

        $bachelorCodes = $registrations->pluck('bachelor_subject_code')->filter()->unique()->values();
        $prsCodes = $registrations->pluck('post_related_subject_code')->filter()->unique()->values();
        $bachelors = BachelorSubject::query()->whereIn('subject_code', $bachelorCodes)->pluck('subject_name','subject_code');
        $prs = PostRelatedSubject::query()->whereIn('subject_code', $prsCodes)->pluck('subject_name','subject_code');

        $allocations = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->whereIn('registration_id', $registrationIds)
            ->get()
            ->keyBy('registration_id');

        // Allocation-ready choices must come from the exact immutable Input Freeze bound to A4/A5,
        // not from reconstructing today's Choice Optimization tables.
        $a4Run = AllocationA4Run::query()->findOrFail((int) $a5->allocation_a4_run_id);
        $frozenCandidates = AllocationInputCandidate::query()
            ->where('input_freeze_id', (int) $a4Run->input_freeze_id)
            ->whereIn('registration_id', $registrationIds)
            ->get(['registration_id','choice_codes','choice_source','skip_reason'])
            ->keyBy('registration_id');

        // Verification explanation evidence comes from the same immutable A2 queue and
        // finalized A4 outcome that produced publication. No report-only allocation rule is run.
        $queueEvidence = AllocationInputQueueEntry::query()
            ->where('input_freeze_id', (int) $a4Run->input_freeze_id)
            ->whereIn('registration_id', $registrationIds)
            ->orderBy('registration_id')->orderBy('choice_position')
            ->get(['registration_id','cadre_code','choice_position','merit_position','eligible_cff','eligible_em','eligible_phc'])
            ->groupBy('registration_id');

        $seatLedgers = AllocationA4SeatLedger::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->get()
            ->keyBy('cadre_code');

        $finalCutoffs = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->selectRaw('cadre_code, allocation_basis, MAX(merit_position) as cutoff_merit')
            ->groupBy('cadre_code','allocation_basis')
            ->get()
            ->groupBy('cadre_code');

        $allA4 = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->orderBy('cadre_code')->orderBy('merit_position')->orderBy('registration_id')
            ->get(['registration_id','cadre_code']);
        $serialByRegistration = [];
        $serials = [];
        foreach ($allA4 as $allocation) {
            $code = (int) $allocation->cadre_code;
            $serials[$code] = ($serials[$code] ?? 0) + 1;
            $serialByRegistration[(int) $allocation->registration_id] = $serials[$code];
        }

        $historicalChoices = ChoiceOptimizationHistoricalChoice::query()
            ->whereIn('registration_id', $registrationIds)
            ->orderBy('registration_id')->orderByDesc('id')
            ->get()->unique('registration_id')->keyBy('registration_id');

        $choiceCodes = collect();
        foreach ($frozenCandidates as $frozen) {
            $choiceCodes = $choiceCodes->merge((array) $frozen->choice_codes);
        }
        $choiceCodes = $choiceCodes->merge($allocations->pluck('cadre_code'))->map(fn ($v) => (int) $v)->filter()->unique()->values();
        $abbr = $this->a6->abbreviations($choiceCodes);

        $rows = $merits->map(function (MeritResult $merit) use (
            $registrations, $bachelors, $prs, $allocations, $serialByRegistration,
            $frozenCandidates, $historicalChoices, $abbr, $specificRanks, $type,
            $queueEvidence, $seatLedgers, $finalCutoffs
        ): array {
            $id = (int) $merit->registration_id;
            $registration = $registrations->get($id);
            $allocation = $allocations->get($id);
            $history = $historicalChoices->get($id);
            $frozen = $frozenCandidates->get($id);
            $choices = array_values(array_filter((array) ($frozen?->choice_codes ?? []), fn ($v) => filled($v)));

            $specificRank = $type === 'technical-cadre'
                ? (int) optional($specificRanks->get($id))->cadre_merit_position
                : null;

            $meritPosition = match ($type) {
                'common' => $merit->common_merit_position,
                'general' => $merit->general_merit_position,
                'technical-only' => $merit->technical_merit_position,
                'technical-cadre' => $specificRank,
                default => null,
            };

            $allocationCode = $allocation ? (int) $allocation->cadre_code : null;
            $allocationAbbr = $allocationCode ? (string) $abbr->get($allocationCode, (string) $allocationCode) : null;
            $higherChoiceReview = $allocation
                ? $this->higherChoiceReview(
                    $queueEvidence->get($id, collect()),
                    (int) $allocation->choice_position,
                    $abbr,
                    $seatLedgers,
                    $finalCutoffs
                )
                : [];

            return [
                'merit_position' => $meritPosition,
                'category' => $this->categoryCode($merit->cadre_category),
                'written_track' => strtoupper((string) ($merit->written_qualified_track ?: '—')),
                'allocation' => $allocation ? sprintf('%s (%d)', $allocationAbbr, (int) ($serialByRegistration[$id] ?? 0)) : '—',
                'allocation_code' => $allocationCode,
                'allocation_abbr' => $allocationAbbr,
                'allocation_serial' => $allocation ? (int) ($serialByRegistration[$id] ?? 0) : null,
                'merit_info' => $this->meritInfo($merit),
                'merit_general' => $merit->general_merit_position !== null ? (int) $merit->general_merit_position : null,
                'merit_technical' => $merit->technical_merit_position !== null ? (int) $merit->technical_merit_position : null,
                'technical_cadre_merits' => $this->technicalCadreMerits($merit),
                'bachelor' => $this->subjectLabel($registration?->bachelor_subject_code, $bachelors),
                'bachelor_code' => filled($registration?->bachelor_subject_code) ? (string) $registration->bachelor_subject_code : null,
                'bachelor_name' => filled($registration?->bachelor_subject_code) ? (string) ($bachelors->get($registration->bachelor_subject_code) ?: 'UNMAPPED') : null,
                'prs' => $this->subjectLabel($registration?->post_related_subject_code, $prs),
                'prs_code' => filled($registration?->post_related_subject_code) ? (string) $registration->post_related_subject_code : null,
                'prs_name' => filled($registration?->post_related_subject_code) ? (string) ($prs->get($registration->post_related_subject_code) ?: 'UNMAPPED') : null,
                'choices' => collect($choices)->map(fn ($code) => [
                    'code' => (int) $code,
                    'abbr' => (string) $abbr->get((int) $code, (string) $code),
                    'allocated' => $allocationCode !== null && (int) $code === $allocationCode,
                ])->values(),
                'higher_choice_review' => $higherChoiceReview,
                'remarks' => $allocation ? '—' : $this->unallocatedRemark($history),
            ];
        })->values();

        $summary = null;
        if ($type === 'technical-cadre' && $cadreCode !== null) {
            $allocated = $rows->filter(fn (array $row) => (int) ($row['allocation_code'] ?? 0) === $cadreCode)->count();
            $total = $rows->count();
            $summary = [
                'eligible' => $total,
                'allocated' => $allocated,
                'non_allocated' => $total - $allocated,
            ];
        }

        return compact('title','cadre','rows','summary');
    }

    private function requireMeritRunId(): int
    {
        $id = $this->a6->currentMeritRunId();
        abort_if($id === null, 409, 'Current finalized Merit source is unavailable.');
        return $id;
    }

    private function categoryCode(mixed $value): string
    {
        $numeric = $value instanceof CadreCategory ? $value->value : (int) $value;
        return CadreCategory::tryFrom($numeric)?->code() ?? '—';
    }

    private function meritInfo(MeritResult $merit): string
    {
        $parts = [];
        if ($merit->general_merit_position !== null) $parts[] = 'General ('.(int) $merit->general_merit_position.')';
        if ($merit->technical_merit_position !== null) $parts[] = 'Technical ('.(int) $merit->technical_merit_position.')';
        foreach ((array) $merit->all_merit_tech as $abbr => $position) {
            if (filled($position)) $parts[] = strtoupper((string) $abbr).' ('.(int) $position.')';
        }
        return $parts === [] ? '—' : implode(', ', $parts);
    }

    /** @return array<int,array{abbr:string,position:int}> */
    private function technicalCadreMerits(MeritResult $merit): array
    {
        $rows = [];
        foreach ((array) $merit->all_merit_tech as $abbr => $position) {
            if (filled($position)) {
                $rows[] = ['abbr' => strtoupper((string) $abbr), 'position' => (int) $position];
            }
        }
        return $rows;
    }

    private function subjectLabel(mixed $code, Collection $names): string
    {
        if (! filled($code)) return '—';
        return (string) $code.' - '.(string) ($names->get($code) ?: 'UNMAPPED');
    }

    /**
     * Explain only choices above the final allocated choice, using immutable queue merit,
     * finalized A4 seat ledger and finalized A4 cutoff evidence.
     *
     * @return array<int,array{cadre:string,details:array<int,string>}>
     */
    private function higherChoiceReview(
        Collection $candidateQueue,
        int $allocatedChoicePosition,
        Collection $abbr,
        Collection $seatLedgers,
        Collection $finalCutoffs
    ): array {
        return $candidateQueue
            ->filter(fn ($q) => (int) $q->choice_position < $allocatedChoicePosition)
            ->sortBy('choice_position')
            ->map(function ($q) use ($abbr, $seatLedgers, $finalCutoffs): array {
                $code = (int) $q->cadre_code;
                $name = (string) $abbr->get($code, (string) $code);
                $candidateMerit = (int) $q->merit_position;
                $ledger = $seatLedgers->get($code);
                $cutoffs = $finalCutoffs->get($code, collect())->keyBy(
                    fn ($row) => strtoupper((string) $row->allocation_basis)
                );

                $meritCutoff = $cutoffs->get('MQ')?->cutoff_merit;
                $parts = [];
                if ($meritCutoff !== null) {
                    $parts[] = "merit allocation ended at merit ".(int) $meritCutoff."; candidate merit was {$candidateMerit}";
                } elseif ((int) ($ledger?->merit_capacity ?? 0) > 0) {
                    $parts[] = "no final MQ allocation was recorded; candidate merit was {$candidateMerit}";
                } else {
                    $parts[] = "no merit seat remained in the finalized seat outcome; candidate merit was {$candidateMerit}";
                }

                $eligible = [];
                if ((bool) $q->eligible_cff) $eligible[] = 'CFF';
                if ((bool) $q->eligible_em) $eligible[] = 'EM';
                if ((bool) $q->eligible_phc) $eligible[] = 'PHC';

                if ($eligible === []) {
                    $parts[] = 'no applicable quota entitlement';
                } else {
                    foreach ($eligible as $quota) {
                        $capacityField = strtolower($quota).'_capacity';
                        $occupiedField = strtolower($quota).'_occupied';
                        $capacity = (int) ($ledger?->{$capacityField} ?? 0);
                        $occupied = (int) ($ledger?->{$occupiedField} ?? 0);
                        $quotaCutoff = $cutoffs->get($quota)?->cutoff_merit;

                        if ($capacity <= 0) {
                            $parts[] = "no {$quota} seat was available in the finalized seat breakup";
                        } elseif ($quotaCutoff !== null) {
                            $parts[] = "{$quota} allocation ended at merit ".(int) $quotaCutoff;
                        } elseif ($occupied <= 0) {
                            $parts[] = "no final {$quota} allocation was recorded";
                        } else {
                            $parts[] = "{$quota} quota was fully accounted for in the finalized outcome";
                        }
                    }
                }

                return [
                    'cadre' => $name,
                    'details' => $parts,
                ];
            })
            ->values()
            ->all();
    }

    private function unallocatedRemark(?ChoiceOptimizationHistoricalChoice $history): string
    {
        if ($history?->matched_cutoff) {
            $cutoff = (array) $history->matched_cutoff;
            $cadre = strtoupper((string) ($cutoff['historical_cadre'] ?? ''));
            $bcs = (string) ($cutoff['historical_bcs_number'] ?? '');
            if ($cadre !== '' && $bcs !== '') {
                // Identity-free verification output: previous registration is deliberately omitted.
                return "Historical Cut-off due to {$cadre} in BCS-{$bcs}";
            }
        }
        if (($history?->track_filter_status ?? null) === 'NO_CHOICE_AFTER_WRITTEN_TRACK_FILTER') {
            return 'No allocation-ready choice remains after Written Track filtering';
        }
        if (($history?->optimization_status ?? null) === 'NO_HIGHER_CHOICE_REMAINS') {
            return 'No higher choice remains after optimization';
        }
        return 'Merit position was outside the available posts for all allocation-ready choices.';
    }

    private function cadreName(int $code): string
    {
        $main = CadreMaster::query()->where('cadre_code', $code)->value('cadre_title');
        if (filled($main)) return (string) $main;
        $sub = CadreSubMaster::query()->where('sub_cadre_code', $code)->value('sub_cadre_title');
        return filled($sub) ? (string) $sub : '—';
    }
}
