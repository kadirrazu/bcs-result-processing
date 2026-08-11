<?php

namespace App\Services\ChoiceValidation;

use App\Enums\CadreType;
use App\Enums\ChoiceValidationReason;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\CircularEntry;
use App\Models\Registration;
use Illuminate\Support\Collection;

final class ChoiceValidationEngine
{
    /** @var array<string,CadreMaster> */
    private array $mainByCode = [];
    /** @var array<string,CadreSubMaster> */
    private array $subByCode = [];
    /** @var array<string,list<CircularEntry>> */
    private array $entriesByEffectiveCode = [];
    /** @var array<string,list<CircularEntry>> */
    private array $subRowsByParentCode = [];
    private bool $prepared = false;

    /** @param Collection<int,CircularEntry> $circularEntries */
    public function prepare(Collection $circularEntries): void
    {
        if ($this->prepared) {
            return;
        }

        $this->mainByCode = CadreMaster::query()->get()->keyBy(fn ($m) => (string) $m->cadre_code)->all();
        $this->subByCode = CadreSubMaster::query()->with('parentCadre')->get()->keyBy(fn ($m) => (string) $m->sub_cadre_code)->all();

        foreach ($circularEntries as $entry) {
            if (strtolower((string) $entry->status) !== 'active') {
                continue;
            }

            $effective = (string) $entry->effective_code;
            $this->entriesByEffectiveCode[$effective][] = $entry;

            if ($entry->sub_cadre_code !== null) {
                $this->subRowsByParentCode[(string) $entry->cadre_code][] = $entry;
            }
        }

        foreach ($this->subRowsByParentCode as &$rows) {
            usort($rows, static fn (CircularEntry $a, CircularEntry $b): int =>
                [($a->sub_serial ?? PHP_INT_MAX), $a->id] <=> [($b->sub_serial ?? PHP_INT_MAX), $b->id]
            );
        }
        unset($rows);

        $this->prepared = true;
    }

    /** @param Collection<int,CircularEntry> $circularEntries */
    public function validate(Registration $registration, array $track, array $sourceItems, Collection $circularEntries): array
    {
        $this->prepare($circularEntries);

        $validated = [];
        $details = [];
        $seen = [];
        $removed = 0;
        $expanded = 0;
        $outPos = 0;

        if (! $track['eligible']) {
            $reasonCode = $track['reason_code'] ?? ChoiceValidationReason::CandidateNotChoiceEligible->value;
            $reasonMessage = $track['reason_message'] ?? 'Candidate is not in the finalized ACTIVE Viva PASS population.';
            $candidateStatus = $track['status'] ?? 'not_applicable';

            foreach ($sourceItems as $item) {
                $details[] = $this->detail(
                    $item,
                    'unknown',
                    'removed',
                    $reasonCode,
                    $reasonMessage,
                );
                $removed++;
            }

            return compact('validated', 'details', 'removed', 'expanded') + [
                'status' => $candidateStatus,
                'reason' => $reasonCode,
            ];
        }

        foreach ($sourceItems as $item) {
            $code = (string) $item->choice_code;

            if (isset($seen[$code])) {
                $details[] = $this->detail($item, 'unknown', 'removed', ChoiceValidationReason::DuplicateChoice->value, 'Duplicate source choice; first occurrence retained.');
                $removed++;
                continue;
            }
            $seen[$code] = true;

            $main = $this->mainByCode[$code] ?? null;
            $sub = $this->subByCode[$code] ?? null;

            if (! $main && ! $sub) {
                $details[] = $this->detail($item, 'unknown', 'removed', ChoiceValidationReason::UnknownCode->value, 'Choice code does not resolve in Cadre/Sub Cadre Master.');
                $removed++;
                continue;
            }

            $resolvedType = $sub ? 'sub' : 'main';
            $active = $sub
                ? ((bool) $sub->is_active && (bool) $sub->parentCadre?->is_active)
                : (bool) $main->is_active;

            if (! $active) {
                $details[] = $this->detail($item, $resolvedType, 'removed', ChoiceValidationReason::InactiveMasterCode->value, 'Choice code resolves to an inactive master record.', $main, $sub);
                $removed++;
                continue;
            }

            if ($main) {
                $subRows = $this->subRowsByParentCode[(string) $main->cadre_code] ?? [];

                if ($subRows !== []) {
                    $eligibleRows = array_values(array_filter(
                        $subRows,
                        fn (CircularEntry $entry): bool => $this->eligibleEntry($entry, $registration, $track['track'])
                    ));

                    if ($eligibleRows === []) {
                        $details[] = $this->detail($item, 'main', 'removed', ChoiceValidationReason::ParentNoEligibleSubCadre->value, 'Parent choice has no eligible finalized Circular sub-cadre for this candidate.', $main, null);
                        $removed++;
                        continue;
                    }

                    foreach ($eligibleRows as $entry) {
                        $output = (string) $entry->effective_code;
                        if (in_array($output, $validated, true)) {
                            continue;
                        }

                        $validated[] = $output;
                        $outPos++;
                        $expanded++;
                        $details[] = $this->detail($item, 'main', 'expanded', null, 'Parent choice expanded to eligible sub-cadre.', $main, null, $outPos, $output, $code, $entry, $this->eligibilitySnapshot($entry, $registration));
                    }
                    continue;
                }
            }

            $matches = $this->entriesByEffectiveCode[$code] ?? [];
            if ($matches === []) {
                $details[] = $this->detail($item, $resolvedType, 'removed', ChoiceValidationReason::NotInFinalizedCircular->value, 'Choice code is not present in the finalized Circular.', $main, $sub);
                $removed++;
                continue;
            }

            $eligible = null;
            foreach ($matches as $match) {
                if ($this->eligibleEntry($match, $registration, $track['track'])) {
                    $eligible = $match;
                    break;
                }
            }

            if (! $eligible) {
                $reason = $this->reasonForIneligible($matches, $registration, $track['track']);
                $first = $matches[0] ?? null;
                $details[] = $this->detail($item, $resolvedType, 'removed', $reason, 'Choice is not eligible for the candidate under the finalized Circular.', $main, $sub, null, null, null, $first, $this->eligibilitySnapshot($first, $registration));
                $removed++;
                continue;
            }

            if (in_array($code, $validated, true)) {
                $details[] = $this->detail($item, $resolvedType, 'removed', ChoiceValidationReason::DuplicateChoice->value, 'Choice resolves to an output code already produced by an earlier source choice.', $main, $sub, null, null, null, $eligible, $this->eligibilitySnapshot($eligible, $registration));
                $removed++;
                continue;
            }

            $validated[] = $code;
            $outPos++;
            $details[] = $this->detail($item, $resolvedType, 'kept', null, 'Choice retained.', $main, $sub, $outPos, $code, null, $eligible, $this->eligibilitySnapshot($eligible, $registration));
        }

        return compact('validated', 'details', 'removed', 'expanded') + [
            'status' => $validated !== [] ? 'valid' : 'no_valid_choices',
            'reason' => $validated === [] ? ChoiceValidationReason::NoValidChoiceRemains->value : null,
        ];
    }

    private function eligibleEntry(CircularEntry $entry, Registration $registration, string $track): bool
    {
        $type = $entry->cadre_type instanceof CadreType ? $entry->cadre_type->value : (string) $entry->cadre_type;

        if ($type === 'GG') {
            return in_array($track, ['general', 'both'], true);
        }

        if ($type !== 'TT' || ! in_array($track, ['technical', 'both'], true)) {
            return false;
        }

        $bachelor = $entry->bachelorSubjects->pluck('subject_code')->map(fn ($v) => (string) $v)->all();
        $prs = $entry->prsSubjects->pluck('prs_code')->map(fn ($v) => (string) $v)->all();

        return in_array((string) $registration->bachelor_subject_code, $bachelor, true)
            && in_array((string) $registration->post_related_subject_code, $prs, true);
    }

    /** @param list<CircularEntry> $matches */
    private function reasonForIneligible(array $matches, Registration $registration, string $track): string
    {
        $first = $matches[0];
        $type = $first->cadre_type instanceof CadreType ? $first->cadre_type->value : (string) $first->cadre_type;

        if (($type === 'GG' && ! in_array($track, ['general', 'both'], true))
            || ($type === 'TT' && ! in_array($track, ['technical', 'both'], true))) {
            return ChoiceValidationReason::TrackNotAllowed->value;
        }

        $bOk = false;
        $pOk = false;
        foreach ($matches as $entry) {
            $bOk = $bOk || in_array((string) $registration->bachelor_subject_code, $entry->bachelorSubjects->pluck('subject_code')->map(fn ($v) => (string) $v)->all(), true);
            $pOk = $pOk || in_array((string) $registration->post_related_subject_code, $entry->prsSubjects->pluck('prs_code')->map(fn ($v) => (string) $v)->all(), true);
        }

        return ! $bOk && ! $pOk
            ? ChoiceValidationReason::BachelorAndPrsMismatch->value
            : (! $bOk ? ChoiceValidationReason::BachelorSubjectMismatch->value : ChoiceValidationReason::PrsMismatch->value);
    }

    private function eligibilitySnapshot(?CircularEntry $entry, Registration $registration): array
    {
        if (! $entry) {
            return [];
        }

        return [
            'candidate_bachelor_subject_code' => $registration->bachelor_subject_code,
            'candidate_prs_code' => $registration->post_related_subject_code,
            'circular_entry_id' => $entry->id,
            'circular_type' => $entry->cadre_type instanceof CadreType ? $entry->cadre_type->value : $entry->cadre_type,
            'allowed_bachelor_subject_codes' => $entry->bachelorSubjects->pluck('subject_code')->values()->all(),
            'allowed_prs_codes' => $entry->prsSubjects->pluck('prs_code')->values()->all(),
        ];
    }

    private function detail($item, string $resolved, string $result, ?string $reason, ?string $message, $main = null, $sub = null, ?int $outputPosition = null, ?string $outputCode = null, ?string $expandedFrom = null, $entry = null, array $snapshot = []): array
    {
        return [
            'source_position' => $item->position,
            'source_column' => $item->source_column,
            'source_code' => $item->choice_code,
            'resolved_type' => $resolved,
            'resolved_cadre_id' => $main?->id ?? $sub?->parent_cadre_id,
            'resolved_sub_cadre_id' => $sub?->id,
            'result' => $result,
            'reason_code' => $reason,
            'reason_message' => $message,
            'output_position' => $outputPosition,
            'output_code' => $outputCode,
            'expanded_from_code' => $expandedFrom,
            'circular_entry_id' => $entry?->id,
            'eligibility_snapshot' => $snapshot,
        ];
    }
}
