<?php

namespace App\Services\Reporting;

use App\Enums\CadreCategory;
use App\Models\AllocationA4Result;
use App\Models\AllocationA4Run;
use App\Models\AllocationInputCandidate;
use App\Models\AllocationInputQueueEntry;
use App\Models\AllocationResultDisposition;
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
use App\Services\Allocation\AllocationResultDispositionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-only Allocation Verification + Booklet Printing reporting.
 *
 * Verification output is identity-free. Booklet output explicitly opts into
 * approved identity fields. Both modes read the same finalized Merit + A4/A5
 * publication authority and never re-run Allocation business rules.
 */
final class AllocationVerificationReportService
{
    public function __construct(
        private readonly AllocationA6ReportService $a6,
        private readonly AllocationResultDispositionService $dispositions,
    ) {}

    /** @return Collection<int,array<string,mixed>> */
    public function generalCadres(AllocationA5Run $a5): Collection
    {
        $meritRunId = $this->requireMeritRunId();
        $a4Run = AllocationA4Run::query()->findOrFail((int) $a5->allocation_a4_run_id);

        $generalMeritQuery = MeritResult::query()
            ->where('processing_run_id', $meritRunId)
            ->whereNotNull('general_merit_position');

        $this->dispositions->applyPublishedOnly(
            $generalMeritQuery,
            $a5,
            'merit_results.registration_id'
        );

        $generalMeritIds = $generalMeritQuery
            ->pluck('registration_id')
            ->map(fn ($id) => (int) $id)
            ->flip();

        $candidateQuery = AllocationInputCandidate::query()
            ->where('input_freeze_id', (int) $a4Run->input_freeze_id);

        $this->dispositions->applyPublishedOnly(
            $candidateQuery,
            $a5,
            'allocation_input_candidates.registration_id'
        );

        $counts = [];
        foreach ($candidateQuery->get(['registration_id', 'choice_codes']) as $candidate) {
            $registrationId = (int) $candidate->registration_id;
            if (! $generalMeritIds->has($registrationId)) {
                continue;
            }

            foreach (collect((array) $candidate->choice_codes)
                ->map(fn ($code) => (int) $code)
                ->filter()
                ->unique() as $code) {
                $counts[$code] = ($counts[$code] ?? 0) + 1;
            }
        }

        return $this->a6->cadres($a5)
            ->filter(function (array $row): bool {
                $type = $row['entry']?->cadre_type;
                $type = $type instanceof \BackedEnum ? $type->value : $type;

                return strtoupper((string) $type) === 'GG';
            })
            ->map(fn (array $row) => [
                'code' => (int) $row['code'],
                'abbr' => (string) $row['abbr'],
                'eligible_count' => (int) ($counts[(int) $row['code']] ?? 0),
            ])
            ->values();
    }

    /** @return Collection<int,array<string,mixed>> */
    public function technicalCadres(AllocationA5Run $a5): Collection
    {
        $meritRunId = $this->requireMeritRunId();
        $eligibleQuery = MeritCadreRank::query()
            ->where('processing_run_id', $meritRunId)
            ->where('cadre_type', 'TT');
        $this->dispositions->applyPublishedOnly(
            $eligibleQuery,
            $a5,
            'merit_cadre_ranks.registration_id'
        );
        $eligible = $eligibleQuery
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

    /** @return array{title:string,cadre:?array,rows:Collection,summary:?array,quotaSummary:?array} */
    /** @return array{title:string,cadre:?array,rows:Collection,summary:?array,quotaSummary:?array} */
    public function build(string $type, AllocationA5Run $a5, ?int $cadreCode = null): array
    {
        $type = strtolower(trim($type));
        $meritRunId = $this->requireMeritRunId();
        $disposition = $this->dispositions->snapshot($a5);
        $key = $this->reportCacheKey('verification', [
            'a5' => (int) $a5->id,
            'a4' => (int) $a5->allocation_a4_run_id,
            'merit' => $meritRunId,
            'disposition_revision' => (int) $disposition['revision'],
            'disposition_hash' => (string) $disposition['hash'],
            'type' => $type,
            'cadre' => (int) ($cadreCode ?? 0),
        ]);

        return Cache::remember($key, now()->addHours(6), fn (): array =>
            $this->buildUncached($type, $a5, $cadreCode)
        );
    }

    /** @return array{title:string,cadre:?array,rows:Collection,summary:?array,quotaSummary:?array,meritHeadingLines:array} */
    public function buildBooklet(string $type, AllocationA5Run $a5, ?int $cadreCode = null): array
    {
        $type = strtolower(trim($type));
        $meritRunId = $this->requireMeritRunId();
        $disposition = $this->dispositions->snapshot($a5);
        $key = $this->reportCacheKey('booklet', [
            'a5' => (int) $a5->id,
            'a4' => (int) $a5->allocation_a4_run_id,
            'merit' => $meritRunId,
            'disposition_revision' => (int) $disposition['revision'],
            'disposition_hash' => (string) $disposition['hash'],
            'type' => $type,
            'cadre' => (int) ($cadreCode ?? 0),
        ]);

        return Cache::remember($key, now()->addHours(6), fn (): array =>
            $this->buildUncached($type, $a5, $cadreCode, true, false)
        );
    }

    private function buildUncached(
        string $type,
        AllocationA5Run $a5,
        ?int $cadreCode = null,
        bool $includeIdentity = false,
        bool $includeHigherChoice = true,
    ): array {
        $meritRunId = $this->requireMeritRunId();
        $type = strtolower(trim($type));

        $base = MeritResult::query()
            ->where('merit_results.processing_run_id', $meritRunId)
            ->select([
                'merit_results.registration_id',
                'merit_results.cadre_category',
                'merit_results.written_qualified_track',
                'merit_results.common_merit_position',
                'merit_results.general_merit_position',
                'merit_results.technical_merit_position',
                'merit_results.all_merit_tech',
            ]);
        $this->applyReportDisposition(
            $base,
            $a5,
            'merit_results.registration_id',
            $includeIdentity
        );

        $title = '';
        $cadre = null;
        $specificRanks = collect();

        if ($type === 'common') {
            $title = $includeIdentity ? 'Common Merit Position Allocation Booklet Printing Report' : 'Common Merit Position Allocation Verification Report';
            $base->whereNotNull('common_merit_position')
                ->orderBy('common_merit_position');
        } elseif ($type === 'general') {
            $title = $includeIdentity ? 'General Cadre Candidate Allocation Booklet Printing Report' : 'General Cadre Candidate Allocation Report';
            // General-side population is authoritative Merit eligibility (GG + GT in the current normalized model).
            $base->whereNotNull('general_merit_position')
                ->orderBy('general_merit_position');
        } elseif ($type === 'technical-only') {
            $title = $includeIdentity ? 'Only Technical Cadre Candidate Allocation Booklet Printing Report' : 'Only Technical Cadre Candidate Allocation Report';
            // Written effective technical-only population is TT + T.
            // T includes candidates who originated as GT but qualified only on the technical side.
            $base->whereIn('written_qualified_track', ['TT', 'T'])
                ->whereNotNull('technical_merit_position')
                ->orderBy('technical_merit_position');
        } elseif ($type === 'quota') {
            $title = $includeIdentity ? 'Quota Candidate Allocation Booklet Printing Report' : 'Quota Candidate Allocation Verification Report';

            // Quota verification is bound to the exact immutable A4 input freeze.
            // Registration remains authoritative for quota entitlement. A5.5 publication
            // disposition is applied exactly as in the rest of Allocation Verification.
            $a4Run = AllocationA4Run::query()->findOrFail((int) $a5->allocation_a4_run_id);
            $inputRegistrationIds = AllocationInputCandidate::query()
                ->where('input_freeze_id', (int) $a4Run->input_freeze_id)
                ->pluck('registration_id');

            $quotaRegistrationQuery = Registration::query()
                ->whereIn('id', $inputRegistrationIds)
                ->where(function ($query): void {
                    $query->where('has_ff_quota', 2)
                        ->orWhere('has_em_quota', 1)
                        ->orWhere('has_phc_quota', 1);
                });

            $this->applyReportDisposition(
                $quotaRegistrationQuery,
                $a5,
                'registrations.id',
                $includeIdentity
            );

            $quotaRegistrationIds = $quotaRegistrationQuery->pluck('id');

            $base->whereIn('registration_id', $quotaRegistrationIds)
                ->orderByRaw('CASE WHEN common_merit_position IS NULL THEN 1 ELSE 0 END')
                ->orderBy('common_merit_position')
                ->orderByRaw('CASE WHEN general_merit_position IS NULL THEN 1 ELSE 0 END')
                ->orderBy('general_merit_position')
                ->orderByRaw('CASE WHEN technical_merit_position IS NULL THEN 1 ELSE 0 END')
                ->orderBy('technical_merit_position');
        } elseif ($type === 'general-cadre') {
            abort_if(! $cadreCode, 404);

            $a4Run = AllocationA4Run::query()->findOrFail((int) $a5->allocation_a4_run_id);
            $cadreRow = $this->a6->cadres($a5)
                ->first(function (array $row) use ($cadreCode): bool {
                    if ((int) $row['code'] !== $cadreCode) {
                        return false;
                    }

                    $cadreType = $row['entry']?->cadre_type;
                    $cadreType = $cadreType instanceof \BackedEnum ? $cadreType->value : $cadreType;

                    return strtoupper((string) $cadreType) === 'GG';
                });

            abort_if(! $cadreRow, 404, 'The requested cadre is not a current General cadre.');

            $candidateQuery = AllocationInputCandidate::query()
                ->where('input_freeze_id', (int) $a4Run->input_freeze_id);

            $this->applyReportDisposition(
                $candidateQuery,
                $a5,
                'allocation_input_candidates.registration_id',
                $includeIdentity
            );

            $eligibleRegistrationIds = $candidateQuery
                ->get(['registration_id', 'choice_codes'])
                ->filter(function (AllocationInputCandidate $candidate) use ($cadreCode): bool {
                    return collect((array) $candidate->choice_codes)
                        ->map(fn ($code) => (int) $code)
                        ->contains($cadreCode);
                })
                ->pluck('registration_id')
                ->map(fn ($id) => (int) $id)
                ->values();

            $base->whereNotNull('general_merit_position')
                ->whereIn('registration_id', $eligibleRegistrationIds)
                ->orderBy('general_merit_position');

            $cadre = [
                'code' => (int) $cadreCode,
                'abbr' => (string) $cadreRow['abbr'],
                'name' => $this->cadreName((int) $cadreCode),
            ];
            $title = $cadre['abbr'].($includeIdentity ? ' General Cadre Allocation Booklet Printing Report' : ' General Cadre Allocation Verification Report');
        } elseif ($type === 'technical-cadre') {
            abort_if(! $cadreCode, 404);
            $rankQuery = MeritCadreRank::query()
                ->where('processing_run_id', $meritRunId)
                ->where('cadre_type', 'TT')
                ->where('cadre_code', $cadreCode);
            $this->applyReportDisposition(
                $rankQuery,
                $a5,
                'merit_cadre_ranks.registration_id',
                $includeIdentity
            );
            $rankRows = $rankQuery
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
            $title = $cadre['abbr'].($includeIdentity ? ' Technical Cadre Allocation Booklet Printing Report' : ' Technical Cadre Allocation Verification Report');
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

        $registrationColumns = [
            'id',
            'bachelor_subject_code',
            'post_related_subject_code',
            'has_ff_quota',
            'has_em_quota',
            'has_phc_quota',
        ];

        if ($includeIdentity) {
            $registrationColumns = array_merge($registrationColumns, [
                'reg',
                'name',
                'father_name',
                'birth_date',
            ]);
        }

        $registrations = Registration::query()
            ->whereIn('id', $registrationIds)
            ->get($registrationColumns)
            ->keyBy('id');

        $dispositionMap = AllocationResultDisposition::query()
            ->where('allocation_a5_run_id', (int) $a5->id)
            ->whereIn('registration_id', $registrationIds)
            ->get(['registration_id', 'status', 'reason'])
            ->keyBy('registration_id');

        $bachelorCodes = $registrations->pluck('bachelor_subject_code')->filter()->unique()->values();
        $prsCodes = $registrations->pluck('post_related_subject_code')->filter()->unique()->values();
        $bachelors = BachelorSubject::query()->whereIn('subject_code', $bachelorCodes)->pluck('subject_name','subject_code');
        $prs = PostRelatedSubject::query()->whereIn('subject_code', $prsCodes)->pluck('subject_name','subject_code');

        $allocations = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->whereIn('registration_id', $registrationIds)
            ->get(['registration_id','cadre_code','choice_position','allocation_basis','merit_position'])
            ->keyBy('registration_id');

        // Allocation-ready choices must come from the exact immutable Input Freeze bound to A4/A5,
        // not from reconstructing today's Choice Optimization tables.
        $a4Run = AllocationA4Run::query()->findOrFail((int) $a5->allocation_a4_run_id);
        $frozenCandidates = AllocationInputCandidate::query()
            ->where('input_freeze_id', (int) $a4Run->input_freeze_id)
            ->whereIn('registration_id', $registrationIds)
            ->get(['registration_id','choice_codes','choice_source','skip_reason'])
            ->keyBy('registration_id');

        // Higher-choice evidence is Verification-only. Booklet reports intentionally omit
        // the column and therefore skip these potentially expensive evidence queries entirely.
        $queueEvidence = collect();
        $finalCutoffs = collect();

        if ($includeHigherChoice) {
            $queueEvidence = AllocationInputQueueEntry::query()
                ->where('input_freeze_id', (int) $a4Run->input_freeze_id)
                ->whereIn('registration_id', $allocations->keys()->map(fn ($id) => (int) $id)->values())
                ->orderBy('registration_id')->orderBy('choice_position')
                ->get(['registration_id','cadre_code','choice_position','merit_position','eligible_cff','eligible_em','eligible_phc'])
                ->groupBy('registration_id');

            $finalCutoffs = AllocationA4Result::query()
                ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
                ->selectRaw('cadre_code, allocation_basis, MAX(merit_position) as cutoff_merit')
                ->groupBy('cadre_code','allocation_basis')
                ->get()
                ->groupBy('cadre_code');
        }

        $allA4Query = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id);
        $this->applyReportDisposition(
            $allA4Query,
            $a5,
            'allocation_a4_results.registration_id',
            $includeIdentity
        );
        $allA4 = $allA4Query
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
            ->get(['id','registration_id','input_choice_codes','historical_recommendations','matched_cutoff'])
            ->unique('registration_id')->keyBy('registration_id');

        $choiceCodes = collect();
        foreach ($frozenCandidates as $frozen) {
            $choiceCodes = $choiceCodes->merge((array) $frozen->choice_codes);
        }
        foreach ($historicalChoices as $historicalChoice) {
            $choiceCodes = $choiceCodes->merge((array) $historicalChoice->input_choice_codes);
        }
        $choiceCodes = $choiceCodes->merge($allocations->pluck('cadre_code'))->map(fn ($v) => (int) $v)->filter()->unique()->values();
        $abbr = $this->a6->abbreviations($choiceCodes);

        $rows = $merits->map(function (MeritResult $merit) use (
            $registrations, $bachelors, $prs, $allocations, $serialByRegistration,
            $frozenCandidates, $historicalChoices, $abbr, $specificRanks, $type,
            $queueEvidence, $finalCutoffs, $includeIdentity, $includeHigherChoice,
            $dispositionMap
        ): array {
            $id = (int) $merit->registration_id;
            $registration = $registrations->get($id);
            $disposition = $dispositionMap->get($id);
            $dispositionStatus = strtoupper((string) ($disposition?->status ?: AllocationResultDispositionService::ACTIVE));
            $dispositionReason = trim((string) ($disposition?->reason ?? ''));
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
                'general-cadre' => $merit->general_merit_position,
                'technical-only' => $merit->technical_merit_position,
                'quota' => $merit->common_merit_position
                    ?? $merit->general_merit_position
                    ?? $merit->technical_merit_position,
                'technical-cadre' => $specificRank,
                default => null,
            };

            $allocationCode = $allocation ? (int) $allocation->cadre_code : null;
            $allocationAbbr = $allocationCode ? (string) $abbr->get($allocationCode, (string) $allocationCode) : null;
            // Missed-choice evidence is only relevant when a candidate was allocated
            // lower in the preference sequence. Unallocated candidates intentionally skip
            // this report-time evidence lookup/calculation.
            $higherChoiceMissedReasons = $includeHigherChoice && $allocation
                ? $this->higherChoiceMissedReasons(
                    $queueEvidence->get($id, collect()),
                    (int) $allocation->choice_position,
                    $abbr,
                    $finalCutoffs
                )
                : [];

            $validatedChoices = array_values(array_filter(
                (array) ($history?->input_choice_codes ?? $choices),
                fn ($v) => filled($v)
            ));
            $historicalCutoff = $this->historicalCutoff($history);

            return [
                'candidate_reg' => $includeIdentity ? (string) ($registration?->reg ?? '') : null,
                'candidate_name' => $includeIdentity ? (string) ($registration?->name ?? '') : null,
                'candidate_father' => $includeIdentity ? (string) ($registration?->father_name ?? '') : null,
                'candidate_dob' => $includeIdentity && $registration?->birth_date
                    ? $registration->birth_date->format('d-m-Y')
                    : null,
                'merit_position' => $meritPosition,
                'category' => $this->categoryCode($merit->cadre_category),
                'written_track' => strtoupper((string) ($merit->written_qualified_track ?: '—')),
                'allocation' => $allocation ? sprintf('%s (%d)', $allocationAbbr, (int) ($serialByRegistration[$id] ?? 0)) : '—',
                'allocation_code' => $allocationCode,
                'allocation_abbr' => $allocationAbbr,
                'allocation_serial' => $allocation ? (int) ($serialByRegistration[$id] ?? 0) : null,
                'allocation_basis' => $allocation ? strtoupper((string) $allocation->allocation_basis) : null,
                'disposition_status' => $dispositionStatus,
                'disposition_reason' => $dispositionReason !== '' ? $dispositionReason : null,
                'is_withheld' => ! $includeIdentity && $dispositionStatus === AllocationResultDispositionService::WITHHELD,
                'merit_info' => $this->meritInfo($merit),
                'merit_general' => $merit->general_merit_position !== null ? (int) $merit->general_merit_position : null,
                'merit_technical' => $merit->technical_merit_position !== null ? (int) $merit->technical_merit_position : null,
                'technical_cadre_merits' => $this->technicalCadreMerits($merit),
                'quota_labels' => $this->quotaLabels($registration),
                'bachelor' => $this->subjectLabel($registration?->bachelor_subject_code, $bachelors),
                'bachelor_code' => filled($registration?->bachelor_subject_code) ? (string) $registration->bachelor_subject_code : null,
                'bachelor_name' => filled($registration?->bachelor_subject_code) ? (string) ($bachelors->get($registration->bachelor_subject_code) ?: 'UNMAPPED') : null,
                'prs' => $this->subjectLabel($registration?->post_related_subject_code, $prs),
                'prs_code' => filled($registration?->post_related_subject_code) ? (string) $registration->post_related_subject_code : null,
                'prs_name' => filled($registration?->post_related_subject_code) ? (string) ($prs->get($registration->post_related_subject_code) ?: 'UNMAPPED') : null,
                'validated_choices' => $this->choiceLabels($validatedChoices, $abbr, null),
                'choices' => $this->choiceLabels($choices, $abbr, $allocationCode),
                'historical_cutoff' => $historicalCutoff,
                'historical_allocations' => $this->historicalAllocations($history),
                'higher_choice_missed_reasons' => $higherChoiceMissedReasons,
                'allocation_outcome' => $allocation ? 'ALLOCATED' : 'NOT_ALLOCATED',
                'remarks' => ! $includeIdentity && $dispositionStatus === AllocationResultDispositionService::WITHHELD
                    ? 'WITHHELD'.($dispositionReason !== '' ? ' — '.$dispositionReason : '')
                    : ($type === 'quota'
                        ? ($allocation ? 'Allocated by '.strtoupper((string) $allocation->allocation_basis) : 'Not Allocated')
                        : null),
            ];
        })->values();

        $summary = null;
        if (in_array($type, ['general-cadre', 'technical-cadre'], true) && $cadreCode !== null) {
            $allocated = $rows
                ->filter(fn (array $row) => (int) ($row['allocation_code'] ?? 0) === $cadreCode)
                ->count();

            $allocatedInOtherCadres = $rows
                ->filter(function (array $row) use ($cadreCode): bool {
                    $allocationCode = (int) ($row['allocation_code'] ?? 0);

                    return $allocationCode > 0 && $allocationCode !== $cadreCode;
                })
                ->count();

            $notAllocatedAnywhere = $rows
                ->filter(fn (array $row): bool => (int) ($row['allocation_code'] ?? 0) === 0)
                ->count();

            $total = $rows->count();
            $totalPost = (int) ($a5->capacityResults()
                ->where('cadre_code', $cadreCode)
                ->value('sanctioned_posts') ?? 0);

            $summary = [
                'cadre_code' => $cadreCode,
                'cadre_abbr' => (string) ($cadre['abbr'] ?? '—'),
                'total_post' => $totalPost,
                'eligible' => $total,
                'allocated' => $allocated,
                'non_allocated' => $total - $allocated,
                'allocated_in_other_cadres' => $allocatedInOtherCadres,
                'not_allocated_anywhere' => $notAllocatedAnywhere,
            ];
        }

        $meritHeadingLines = match ($type) {
            'common' => ['COMMON MERIT', 'POSITION'],
            'general', 'general-cadre' => ['GENERAL MERIT', 'POSITION'],
            'technical-only' => ['TECHNICAL MERIT', 'POSITION'],
            'technical-cadre' => [strtoupper((string) ($cadre['abbr'] ?? 'TECHNICAL')).' MERIT', 'POSITION'],
            'quota' => ['APPLICABLE MERIT', 'POSITION'],
            default => ['MERIT', 'POSITION'],
        };

        $quotaSummary = null;
        if ($type === 'quota') {
            $quotaSummary = [
                'total' => $rows->count(),
                'cff' => $rows->filter(fn (array $row): bool => in_array('CFF', $row['quota_labels'], true))->count(),
                'em' => $rows->filter(fn (array $row): bool => in_array('EM', $row['quota_labels'], true))->count(),
                'phc' => $rows->filter(fn (array $row): bool => in_array('PHC', $row['quota_labels'], true))->count(),
                'allocated' => $rows->where('allocation_outcome', 'ALLOCATED')->count(),
                'not_allocated' => $rows->where('allocation_outcome', 'NOT_ALLOCATED')->count(),
                'allocated_mq' => $rows->filter(fn (array $row): bool => $row['allocation_outcome'] === 'ALLOCATED' && ($row['allocation_basis'] ?? null) === 'MQ')->count(),
                'allocated_cff' => $rows->filter(fn (array $row): bool => $row['allocation_outcome'] === 'ALLOCATED' && ($row['allocation_basis'] ?? null) === 'CFF')->count(),
                'allocated_em' => $rows->filter(fn (array $row): bool => $row['allocation_outcome'] === 'ALLOCATED' && ($row['allocation_basis'] ?? null) === 'EM')->count(),
                'allocated_phc' => $rows->filter(fn (array $row): bool => $row['allocation_outcome'] === 'ALLOCATED' && ($row['allocation_basis'] ?? null) === 'PHC')->count(),
            ];
        }

        return compact('title','cadre','rows','summary','quotaSummary','meritHeadingLines');
    }

    /**
     * Cadre-wise final ACTIVE allocation serial / merit / basis verification.
     *
     * The candidate population is the current A4 allocation authority filtered
     * through A5.5 publication disposition. WITHHELD/CANCELLED never appear.
     * General cadres use finalized General Merit; Technical cadres use finalized
     * Technical Merit. Allocation Basis is the exact A4 basis (MQ/CFF/EM/PHC).
     *
     * @return array{title:string,rows_per_group:int,sections:Collection}
     */
    /** @return array{title:string,rows_per_group:int,sections:Collection} */
    public function buildCadreSerialMerit(AllocationA5Run $a5): array
    {
        $meritRunId = $this->requireMeritRunId();
        $disposition = $this->dispositions->snapshot($a5);
        $key = $this->reportCacheKey('cadre-serial-merit', [
            'a5' => (int) $a5->id,
            'a4' => (int) $a5->allocation_a4_run_id,
            'merit' => $meritRunId,
            'disposition_revision' => (int) $disposition['revision'],
            'disposition_hash' => (string) $disposition['hash'],
        ]);

        return Cache::remember($key, now()->addHours(6), fn (): array =>
            $this->buildCadreSerialMeritUncached($a5)
        );
    }

    private function buildCadreSerialMeritUncached(AllocationA5Run $a5): array
    {
        $meritRunId = $this->requireMeritRunId();

        $allocationQuery = AllocationA4Result::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id);

        $this->dispositions->applyPublishedOnly(
            $allocationQuery,
            $a5,
            'allocation_a4_results.registration_id'
        );

        $allocations = $allocationQuery
            ->get([
                'registration_id',
                'cadre_code',
                'allocation_basis',
                'merit_position',
            ]);

        $registrationIds = $allocations
            ->pluck('registration_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $merits = MeritResult::query()
            ->where('processing_run_id', $meritRunId)
            ->whereIn('registration_id', $registrationIds)
            ->get([
                'registration_id',
                'general_merit_position',
                'technical_merit_position',
            ])
            ->keyBy('registration_id');

        $allocationsByCadre = $allocations->groupBy(
            fn ($row) => (int) $row->cadre_code
        );

        $rowsPerGroup = 25;

        $sections = $this->a6->cadres($a5)
            ->map(function (array $cadreRow) use ($allocationsByCadre, $merits, $rowsPerGroup): array {
                $code = (int) $cadreRow['code'];
                $entry = $cadreRow['entry'];
                $capacity = $cadreRow['capacity'];

                $cadreType = $entry?->cadre_type;
                $cadreType = $cadreType instanceof \BackedEnum ? $cadreType->value : $cadreType;
                $isTechnical = strtoupper((string) $cadreType) === 'TT';

                $candidateRows = collect($allocationsByCadre->get($code, collect()))
                    ->map(function ($allocation) use ($merits, $isTechnical): array {
                        $merit = $merits->get((int) $allocation->registration_id);
                        $meritPosition = $isTechnical
                            ? $merit?->technical_merit_position
                            : $merit?->general_merit_position;

                        return [
                            'registration_id' => (int) $allocation->registration_id,
                            'merit_position' => $meritPosition === null ? null : (int) $meritPosition,
                            'allocation_basis' => strtoupper((string) ($allocation->allocation_basis ?: '—')),
                            'fallback_merit_position' => $allocation->merit_position === null
                                ? null
                                : (int) $allocation->merit_position,
                        ];
                    })
                    ->sort(function (array $a, array $b): int {
                        $aOrder = $a['fallback_merit_position'] ?? PHP_INT_MAX;
                        $bOrder = $b['fallback_merit_position'] ?? PHP_INT_MAX;

                        return [$aOrder, $a['registration_id']] <=> [$bOrder, $b['registration_id']];
                    })
                    ->values()
                    ->map(function (array $row, int $index): array {
                        $row['serial'] = $index + 1;
                        unset($row['fallback_merit_position'], $row['registration_id']);

                        return $row;
                    })
                    ->values();

                $pages = $candidateRows
                    ->chunk($rowsPerGroup * 3)
                    ->map(function (Collection $pageRows) use ($rowsPerGroup): array {
                        $groups = $pageRows->chunk($rowsPerGroup)->values();

                        return [
                            'groups' => collect([0, 1, 2])
                                ->map(fn (int $index) => collect($groups->get($index, collect()))->values())
                                ->values(),
                        ];
                    })
                    ->values();

                if ($pages->isEmpty()) {
                    $pages = collect([[
                        'groups' => collect([collect(), collect(), collect()]),
                    ]]);
                }

                $headingParts = collect([
                    (string) ($entry?->cadre_name_snapshot ?? ''),
                    (string) ($entry?->post_name_snapshot ?? ''),
                ])->map(fn (string $value) => trim($value))
                    ->filter()
                    ->unique()
                    ->values();

                $heading = $code.' - '.(string) $cadreRow['abbr'];
                if ($headingParts->isNotEmpty()) {
                    $heading .= ' - '.$headingParts->implode(' - ');
                }

                return [
                    'code' => $code,
                    'abbr' => (string) $cadreRow['abbr'],
                    'heading' => $heading,
                    'cadre_type' => $isTechnical ? 'TT' : 'GG',
                    'merit_label' => $isTechnical ? 'Technical Merit Position' : 'General Merit Position',
                    'total_post' => (int) $capacity->sanctioned_posts,
                    'total_allocated' => $candidateRows->count(),
                    'pages' => $pages,
                ];
            })
            ->filter(fn (array $section): bool => (int) $section['total_allocated'] > 0)
            ->values();

        return [
            'title' => 'Cadre-wise Serial, Merit & Allocation Basis Verification Report',
            'rows_per_group' => $rowsPerGroup,
            'sections' => $sections,
        ];
    }

    /** @param array<string,mixed> $parts */
    /**
     * Verification reflects allocation truth: ACTIVE + WITHHELD, CANCELLED excluded.
     * Booklet reflects publishable truth: ACTIVE only.
     */
    private function applyReportDisposition(
        \Illuminate\Database\Eloquent\Builder $query,
        AllocationA5Run $a5,
        string $registrationColumn,
        bool $booklet,
    ): \Illuminate\Database\Eloquent\Builder {
        if ($booklet) {
            return $this->dispositions->applyPublishedOnly($query, $a5, $registrationColumn);
        }

        return $query->whereNotExists(function ($sub) use ($a5, $registrationColumn): void {
            $sub->selectRaw('1')
                ->from('allocation_result_dispositions as ard_report')
                ->whereColumn('ard_report.registration_id', $registrationColumn)
                ->where('ard_report.allocation_a5_run_id', $a5->id)
                ->where('ard_report.status', AllocationResultDispositionService::CANCELLED);
        });
    }

    private function reportCacheKey(string $scope, array $parts): string
    {
        $database = (string) config('database.connections.exam.database', 'exam');
        ksort($parts);

        return 'reporting:allocation-verification:'.hash('sha256', json_encode([
            'db' => $database,
            'scope' => $scope,
            'parts' => $parts,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
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

    /** @return array<int,string> */
    private function quotaLabels(?Registration $registration): array
    {
        if (! $registration) return [];

        $labels = [];
        if ((int) $registration->has_ff_quota === 2) $labels[] = 'CFF';
        if ((int) $registration->has_em_quota === 1) $labels[] = 'EM';
        if ((int) $registration->has_phc_quota === 1) $labels[] = 'PHC';

        return $labels;
    }

    /** @return Collection<int,array{code:int,abbr:string,allocated:bool}> */
    private function choiceLabels(array $codes, Collection $abbr, ?int $allocationCode): Collection
    {
        return collect($codes)->map(fn ($code) => [
            'code' => (int) $code,
            'abbr' => (string) $abbr->get((int) $code, (string) $code),
            'allocated' => $allocationCode !== null && (int) $code === $allocationCode,
        ])->values();
    }

    /** @return array{cadre:string,bcs:string}|null */
    private function historicalCutoff(?ChoiceOptimizationHistoricalChoice $history): ?array
    {
        if (! $history?->matched_cutoff) return null;

        $cutoff = (array) $history->matched_cutoff;
        $cadre = strtoupper((string) ($cutoff['historical_cadre'] ?? ''));
        $bcs = (string) ($cutoff['historical_bcs_number'] ?? '');

        return $cadre !== '' && $bcs !== '' ? ['cadre' => $cadre, 'bcs' => 'BCS-'.$bcs] : null;
    }

    /**
     * Compact previous-BCS cadre history retained by finalized Choice Optimization.
     * Candidate identity / previous registration values are deliberately excluded.
     * Institutional Previous BCS Repository evidence is labelled Archive; accepted
     * candidate-submitted Google Form evidence is labelled Google.
     *
     * @return array<int,array{bcs:string,cadres:array<int,array{cadre:string,sources:array<int,string>}>}>
     */
    private function historicalAllocations(?ChoiceOptimizationHistoricalChoice $history): array
    {
        $grouped = [];

        foreach ((array) ($history?->historical_recommendations ?? []) as $recommendation) {
            $recommendation = (array) $recommendation;
            $bcs = (int) ($recommendation['bcs_number'] ?? 0);
            $cadre = strtoupper(trim((string) ($recommendation['cadre'] ?? '')));

            if ($bcs <= 0 || $cadre === '') {
                continue;
            }

            $labels = collect((array) ($recommendation['sources'] ?? []))
                ->map(function (mixed $source): ?string {
                    $source = (array) $source;

                    return match (strtolower(trim((string) ($source['source'] ?? '')))) {
                        'previous_bcs_repository' => 'Archive',
                        'google_form' => 'Google',
                        default => null,
                    };
                })
                ->filter()
                ->unique()
                ->values()
                ->all();

            if ($labels === []) {
                $labels = ['Archive'];
            }

            $grouped[$bcs] ??= [];
            $grouped[$bcs][$cadre] ??= [];

            foreach ($labels as $label) {
                if (! in_array($label, $grouped[$bcs][$cadre], true)) {
                    $grouped[$bcs][$cadre][] = $label;
                }
            }
        }

        krsort($grouped, SORT_NUMERIC);

        return collect($grouped)
            ->map(function (array $cadres, int|string $bcs): array {
                ksort($cadres, SORT_STRING);

                return [
                    'bcs' => 'BCS-'.(int) $bcs,
                    'cadres' => collect($cadres)
                        ->map(fn (array $sources, string $cadre): array => [
                            'cadre' => $cadre,
                            'sources' => array_values($sources),
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
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
     * Compact missed-choice evidence from the immutable A2 queue and final A4 cutoffs.
     *
     * Only choices above the final allocated choice are shown. Unallocated candidates
     * intentionally do not request missed-choice evidence in verification output.
     * MQ last merit is always included. Only quota bases applicable to the candidate
     * for that cadre are additionally included. No report-time allocation is re-run.
     *
     * @return array<int,array{cadre:string,last_merits:array<string,?int>}>
     */
    private function higherChoiceMissedReasons(
        Collection $candidateQueue,
        int $allocatedChoicePosition,
        Collection $abbr,
        Collection $finalCutoffs
    ): array {
        return $candidateQueue
            ->filter(fn ($q) => (int) $q->choice_position < $allocatedChoicePosition)
            ->sortBy('choice_position')
            ->map(function ($q) use ($abbr, $finalCutoffs): array {
                $code = (int) $q->cadre_code;
                $cutoffs = $finalCutoffs->get($code, collect())->keyBy(
                    fn ($row) => strtoupper((string) $row->allocation_basis)
                );

                $lastMerits = [
                    'MQ' => $cutoffs->get('MQ')?->cutoff_merit !== null
                        ? (int) $cutoffs->get('MQ')->cutoff_merit
                        : null,
                ];

                if ((bool) $q->eligible_cff) {
                    $lastMerits['CFF'] = $cutoffs->get('CFF')?->cutoff_merit !== null
                        ? (int) $cutoffs->get('CFF')->cutoff_merit
                        : null;
                }
                if ((bool) $q->eligible_em) {
                    $lastMerits['EM'] = $cutoffs->get('EM')?->cutoff_merit !== null
                        ? (int) $cutoffs->get('EM')->cutoff_merit
                        : null;
                }
                if ((bool) $q->eligible_phc) {
                    $lastMerits['PHC'] = $cutoffs->get('PHC')?->cutoff_merit !== null
                        ? (int) $cutoffs->get('PHC')->cutoff_merit
                        : null;
                }

                return [
                    'cadre' => (string) $abbr->get($code, (string) $code),
                    'last_merits' => $lastMerits,
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
        $main = CadreMaster::query()->where('cadre_code', $code)->value('cadre_name');
        if (filled($main)) return (string) $main;

        $sub = CadreSubMaster::query()->where('sub_cadre_code', $code)->value('post_name');
        return filled($sub) ? (string) $sub : '—';
    }
}
