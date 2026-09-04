<?php

namespace App\Services\Allocation;

use App\Enums\CadreCategory;
use App\Enums\CadreType;
use App\Models\AllocationA4Result;
use App\Models\AllocationA4Run;
use App\Models\AllocationA5CandidateResult;
use App\Models\AllocationA5CapacityResult;
use App\Models\AllocationA5Run;
use App\Models\AllocationProcessingAudit;
use App\Models\CircularEntry;
use App\Models\Registration;
use App\Services\Circular\CircularFinalizedDatasetService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * A5 is a read-only assurance gate over the exact completed A4 result.
 *
 * It NEVER reallocates candidates, releases seats, changes quota basis, or
 * mutates A4 evidence. It independently verifies final allocation validity
 * against current authoritative Circular + Registration data and persists a
 * reproducible validation report for operator review/finalization.
 */
final class AllocationA5ValidityService
{
    public function __construct(private readonly CircularFinalizedDatasetService $circular) {}

    /** @param null|callable(string,int,string,int,int):void $progress */
    public function process(AllocationA5Run $run, ?callable $progress = null): AllocationA5Run
    {
        $run = AllocationA5Run::query()->whereKey($run->id)->firstOrFail();
        $a4 = AllocationA4Run::query()->whereKey($run->allocation_a4_run_id)->firstOrFail();

        $this->assertCurrentA4($a4);
        $confirmation = $this->circular->verifiedConfirmation();
        $entries = $this->circular->entries()->keyBy('id');

        if (! $a4->a4_output_hash || ! hash_equals((string) $run->a4_output_hash, (string) $a4->a4_output_hash)) {
            throw ValidationException::withMessages(['allocation_a5' => 'A5 source binding failed: A4 output hash changed or is missing.']);
        }
        if ((int) $run->circular_version !== (int) $confirmation->version
            || ! hash_equals((string) $run->circular_hash, (string) $confirmation->dataset_hash)) {
            throw ValidationException::withMessages(['allocation_a5' => 'A5 source binding failed: current finalized Circular no longer matches the queued A5 source.']);
        }

        $allocated = AllocationA4Result::query()
            ->where('allocation_a4_run_id', $a4->id)
            ->where('decision_status', 'FINAL')
            ->orderBy('registration_id')
            ->get();

        if ($allocated->count() !== (int) $a4->allocated_count) {
            throw new RuntimeException('A5_A4_ALLOCATED_COUNT_MISMATCH: final A4 result rows do not match A4 allocated_count.');
        }

        $registrations = Registration::query()
            ->whereIn('id', $allocated->pluck('registration_id')->unique()->values())
            ->get()
            ->keyBy('id');

        $registrationHash = $this->registrationHash($allocated, $registrations);
        if ($progress) $progress('VALIDATING_CANDIDATES', 10, 'Validating final A4 candidates against Circular and Registration.', 0, $allocated->count());

        $candidateRows = [];
        $passed = 0;
        $failed = 0;

        foreach ($allocated as $index => $result) {
            $entry = $entries->get((int) $result->circular_entry_id);
            $registration = $registrations->get((int) $result->registration_id);
            $candidate = $this->validateCandidate($result, $entry, $registration);
            $candidateRows[] = $candidate;
            $candidate['overall_status'] === 'PASS' ? $passed++ : $failed++;

            if (($index + 1) % 100 === 0 || ($index + 1) === $allocated->count()) {
                $pct = 10 + (int) floor((($index + 1) / max(1, $allocated->count())) * 65);
                if ($progress) $progress('VALIDATING_CANDIDATES', min(75, $pct), 'Validating final candidate eligibility and quota entitlement.', $index + 1, $allocated->count());
            }
        }

        /*
         * Seat-limit validation is intentionally executed AFTER all candidate
         * validity checks. It verifies final occupancy only; it does not attempt
         * to repair an over-allocation or re-run any allocation algorithm.
         */
        if ($progress) $progress('VALIDATING_CAPACITY', 82, 'Checking final allocated counts against Circular sanctioned post limits.', 0, $entries->count());
        [$capacityRows, $capacityFailed] = $this->validateCapacity($a4, $entries);

        $candidateHash = $this->hashRows($candidateRows, [
            'allocation_a4_run_id' => (int) $a4->id,
            'a4_output_hash' => (string) $a4->a4_output_hash,
            'registration_hash' => $registrationHash,
            'circular_hash' => (string) $confirmation->dataset_hash,
        ]);
        $capacityHash = $this->hashRows($capacityRows, [
            'allocation_a4_run_id' => (int) $a4->id,
            'circular_hash' => (string) $confirmation->dataset_hash,
        ]);

        $status = ($failed === 0 && $capacityFailed === 0) ? 'validated_ok' : 'validated_failed';

        DB::connection('exam')->transaction(function () use (
            $run, $candidateRows, $capacityRows, $registrationHash, $candidateHash, $capacityHash,
            $allocated, $passed, $failed, $capacityFailed, $status
        ): void {
            $locked = AllocationA5Run::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            AllocationA5CandidateResult::query()->where('allocation_a5_run_id', $locked->id)->delete();
            AllocationA5CapacityResult::query()->where('allocation_a5_run_id', $locked->id)->delete();

            foreach (array_chunk($candidateRows, 500) as $chunk) {
                AllocationA5CandidateResult::query()->insert(array_map(fn (array $row): array => $row + [
                    'allocation_a5_run_id' => (int) $locked->id,
                    'created_at' => now(), 'updated_at' => now(),
                ], $chunk));
            }
            foreach (array_chunk($capacityRows, 250) as $chunk) {
                AllocationA5CapacityResult::query()->insert(array_map(fn (array $row): array => $row + [
                    'allocation_a5_run_id' => (int) $locked->id,
                    'created_at' => now(), 'updated_at' => now(),
                ], $chunk));
            }

            $locked->forceFill([
                'status' => $status,
                'phase' => 'VALIDATION_COMPLETE',
                'registration_hash' => $registrationHash,
                'candidate_result_hash' => $candidateHash,
                'capacity_result_hash' => $capacityHash,
                'total_allocated' => $allocated->count(),
                'candidate_passed' => $passed,
                'candidate_failed' => $failed,
                'capacity_checked' => count($capacityRows),
                'capacity_failed' => $capacityFailed,
                'progress_percent' => 100,
                'progress_current' => $allocated->count(),
                'progress_total' => $allocated->count(),
                'progress_message' => $status === 'validated_ok'
                    ? 'A5 validation completed: 100% PASS. Ready for operator finalization.'
                    : 'A5 validation completed with blocking failures. Review the validation report.',
                'completed_at' => now(),
                'failure_message' => null,
            ])->save();

            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A5_VALIDATION_COMPLETED',
                'actor_id' => $locked->started_by,
                'from_status' => 'running',
                'to_status' => $status,
                'context' => [
                    'allocation_a5_run_id' => (int) $locked->id,
                    'candidate_passed' => $passed,
                    'candidate_failed' => $failed,
                    'capacity_failed' => $capacityFailed,
                    'candidate_result_hash' => $candidateHash,
                    'capacity_result_hash' => $capacityHash,
                ],
                'created_at' => now(),
            ]);
        });

        return $run->refresh();
    }

    public function finalize(AllocationA5Run $run, ?int $actorId): AllocationA5Run
    {
        $run = AllocationA5Run::query()->whereKey($run->id)->firstOrFail();
        if ((string) $run->status !== 'validated_ok' || $run->candidate_failed > 0 || $run->capacity_failed > 0 || $run->is_stale) {
            throw ValidationException::withMessages(['allocation_a5' => 'A5 can be finalized only when the complete validation is current and 100% PASS.']);
        }

        $a4 = AllocationA4Run::query()->whereKey($run->allocation_a4_run_id)->firstOrFail();
        $this->assertCurrentA4($a4);
        $confirmation = $this->circular->verifiedConfirmation();
        if ((int) $run->circular_version !== (int) $confirmation->version
            || ! hash_equals((string) $run->circular_hash, (string) $confirmation->dataset_hash)) {
            throw ValidationException::withMessages(['allocation_a5' => 'Current Circular changed after A5 validation. Re-run A5.']);
        }

        $allocated = AllocationA4Result::query()->where('allocation_a4_run_id', $a4->id)->where('decision_status', 'FINAL')->orderBy('registration_id')->get();
        $registrations = Registration::query()->whereIn('id', $allocated->pluck('registration_id')->unique())->get()->keyBy('id');
        if (! hash_equals((string) $run->registration_hash, $this->registrationHash($allocated, $registrations))) {
            throw ValidationException::withMessages(['allocation_a5' => 'Authoritative Registration data changed after A5 validation. Re-run A5.']);
        }

        return DB::connection('exam')->transaction(function () use ($run, $actorId): AllocationA5Run {
            $locked = AllocationA5Run::query()->whereKey($run->id)->lockForUpdate()->firstOrFail();
            AllocationA5Run::query()->where('status', 'finalized')->whereKeyNot($locked->id)->update([
                'status' => 'superseded', 'is_stale' => true,
                'stale_reason' => "Superseded by finalized A5 v{$locked->version}.", 'staled_at' => now(), 'updated_at' => now(),
            ]);
            $locked->forceFill(['status' => 'finalized', 'phase' => 'FINALIZED', 'finalized_by' => $actorId, 'finalized_at' => now()])->save();
            AllocationProcessingAudit::query()->create([
                'event' => 'ALLOCATION_A5_FINALIZED', 'actor_id' => $actorId,
                'from_status' => 'validated_ok', 'to_status' => 'finalized',
                'context' => ['allocation_a5_run_id' => (int) $locked->id, 'version' => (int) $locked->version],
                'created_at' => now(),
            ]);
            return $locked->refresh();
        });
    }

    /** @return array<string,mixed> */
    private function validateCandidate(AllocationA4Result $result, ?CircularEntry $entry, ?Registration $registration): array
    {
        $reasons = [];
        $bStatus = 'NOT_APPLICABLE';
        $prsStatus = 'NOT_APPLICABLE';
        $technicalStatus = 'NOT_APPLICABLE';
        $quotaStatus = 'NOT_APPLICABLE';
        $allowedB = [];
        $allowedP = [];

        if (! $entry || (int) $entry->effective_code !== (int) $result->cadre_code || (string) $entry->status !== 'active') {
            $reasons[] = 'ALLOCATED_CADRE_NOT_FOUND_IN_CURRENT_CIRCULAR';
        } else {
            $allowedB = $entry->bachelorSubjects->pluck('subject_code')->map(fn ($v) => (string) $v)->sort()->values()->all();
            $allowedP = $entry->prsSubjects->pluck('prs_code')->map(fn ($v) => (string) $v)->sort()->values()->all();
        }

        if (! $registration) {
            $reasons[] = 'REGISTRATION_DATA_MISSING';
            $bStatus = $prsStatus = $technicalStatus = $quotaStatus = 'FAIL';
        } else {
            if ($allowedB !== []) {
                $bStatus = in_array((string) $registration->bachelor_subject_code, $allowedB, true) ? 'PASS' : 'FAIL';
                if ($bStatus === 'FAIL') $reasons[] = 'BACHELOR_SUBJECT_MISMATCH';
            }
            if ($allowedP !== []) {
                $prsStatus = in_array((string) $registration->post_related_subject_code, $allowedP, true) ? 'PASS' : 'FAIL';
                if ($prsStatus === 'FAIL') $reasons[] = 'POST_RELATED_SUBJECT_MISMATCH';
            }

            $type = $entry?->cadre_type instanceof CadreType ? $entry->cadre_type->value : (string) ($entry?->cadre_type ?? $result->cadre_type);
            if ($type === 'TT') {
                $categoryCode = $registration->cadre_category instanceof CadreCategory ? $registration->cadre_category->code() : null;
                if ($allowedB === [] || $allowedP === []) {
                    $technicalStatus = 'FAIL';
                    $reasons[] = 'CIRCULAR_ELIGIBILITY_RULE_MISSING';
                } elseif (! in_array($categoryCode, ['TT','GT'], true)) {
                    $technicalStatus = 'FAIL';
                    $reasons[] = 'TECHNICAL_ELIGIBILITY_MISMATCH';
                } elseif ($bStatus === 'PASS' && $prsStatus === 'PASS') {
                    $technicalStatus = 'PASS';
                } else {
                    $technicalStatus = 'FAIL';
                    $reasons[] = 'TECHNICAL_ELIGIBILITY_MISMATCH';
                }
            }

            $quotaStatus = $this->quotaStatus((string) $result->allocation_basis, $registration);
            if ($quotaStatus === 'FAIL') $reasons[] = 'QUOTA_ELIGIBILITY_MISMATCH';
        }

        $reasons = array_values(array_unique($reasons));
        return [
            'allocation_a4_result_id' => (int) $result->id,
            'registration_id' => (int) $result->registration_id,
            'reg' => (string) $result->reg,
            'circular_entry_id' => (int) $result->circular_entry_id,
            'cadre_code' => (int) $result->cadre_code,
            'cadre_type' => (string) $result->cadre_type,
            'allocation_basis' => (string) $result->allocation_basis,
            'bachelor_status' => $bStatus,
            'prs_status' => $prsStatus,
            'technical_status' => $technicalStatus,
            'quota_status' => $quotaStatus,
            'overall_status' => $reasons === [] ? 'PASS' : 'FAIL',
            'reason_codes' => json_encode($reasons, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            'candidate_bachelor_subject_code' => $registration?->bachelor_subject_code !== null ? (string) $registration->bachelor_subject_code : null,
            'candidate_prs_code' => $registration?->post_related_subject_code !== null ? (string) $registration->post_related_subject_code : null,
            'allowed_bachelor_subject_codes' => json_encode($allowedB, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            'allowed_prs_codes' => json_encode($allowedP, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            'registration_quota_snapshot' => json_encode([
                'CFF' => (bool) ($registration?->has_ff_quota ?? false),
                'EM' => (bool) ($registration?->has_em_quota ?? false),
                'PHC' => (bool) ($registration?->has_phc_quota ?? false),
            ], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        ];
    }

    private function quotaStatus(string $basis, Registration $registration): string
    {
        return match ($basis) {
            'CFF' => (bool) $registration->has_ff_quota ? 'PASS' : 'FAIL',
            'EM' => (bool) $registration->has_em_quota ? 'PASS' : 'FAIL',
            'PHC' => (bool) $registration->has_phc_quota ? 'PASS' : 'FAIL',
            default => 'NOT_APPLICABLE', // MQ/NM/shifted merit basis requires no quota entitlement.
        };
    }

    /** @return array{0:list<array<string,mixed>>,1:int} */
    private function validateCapacity(AllocationA4Run $a4, $entries): array
    {
        $counts = AllocationA4Result::query()
            ->where('allocation_a4_run_id', $a4->id)
            ->where('decision_status', 'FINAL')
            ->selectRaw('circular_entry_id, COUNT(*) as aggregate')
            ->groupBy('circular_entry_id')
            ->pluck('aggregate', 'circular_entry_id');

        $rows = [];
        $failed = 0;
        foreach ($entries as $entry) {
            if ((string) $entry->status !== 'active') continue;
            $allocated = (int) ($counts[(int) $entry->id] ?? 0);
            $sanctioned = (int) $entry->post_count;
            $status = $allocated <= $sanctioned ? 'PASS' : 'FAIL';
            if ($status === 'FAIL') $failed++;
            $rows[] = [
                'circular_entry_id' => (int) $entry->id,
                'cadre_code' => (int) $entry->effective_code,
                'sanctioned_posts' => $sanctioned,
                'allocated_count' => $allocated,
                'remaining_posts' => $sanctioned - $allocated,
                'status' => $status,
                'reason_code' => $status === 'FAIL' ? 'CADRE_SEAT_LIMIT_EXCEEDED' : null,
            ];
        }
        return [$rows, $failed];
    }

    private function assertCurrentA4(AllocationA4Run $a4): void
    {
        $latest = AllocationA4Run::query()->where('status', 'a4_complete')->where('is_stale', false)->latest('version')->first();
        if (! $latest || (int) $latest->id !== (int) $a4->id || (bool) $a4->is_stale) {
            throw ValidationException::withMessages(['allocation_a5' => 'A5 requires the latest current, non-stale completed A4 Phase-2 result.']);
        }
    }

    private function registrationHash($allocated, $registrations): string
    {
        $rows = $allocated->map(function ($result) use ($registrations): array {
            $r = $registrations->get((int) $result->registration_id);
            return [
                'registration_id' => (int) $result->registration_id,
                'reg' => (string) $result->reg,
                'cadre_category' => $r?->cadre_category instanceof CadreCategory ? $r->cadre_category->value : $r?->cadre_category,
                'bachelor_subject_code' => $r?->bachelor_subject_code,
                'post_related_subject_code' => $r?->post_related_subject_code,
                'has_ff_quota' => (bool) ($r?->has_ff_quota ?? false),
                'has_em_quota' => (bool) ($r?->has_em_quota ?? false),
                'has_phc_quota' => (bool) ($r?->has_phc_quota ?? false),
            ];
        })->values()->all();
        return hash('sha256', json_encode($rows, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }

    private function hashRows(array $rows, array $meta): string
    {
        return hash('sha256', json_encode(['meta' => $meta, 'rows' => $rows], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }
}
