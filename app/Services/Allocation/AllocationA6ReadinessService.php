<?php

namespace App\Services\Allocation;

use App\Models\AllocationA4Run;
use App\Models\AllocationA5Run;
use Illuminate\Validation\ValidationException;

/**
 * Lightweight publishing/read gate for Allocation Reporting & Export (A6).
 *
 * A6 is a consumer of the already-verified/finalized Allocation result. It must
 * not repeat the expensive A1-A5 upstream integrity chain. Instead A6 verifies
 * only the authority it directly publishes:
 *
 * - latest A5 is finalized, current and 100% PASS;
 * - latest current A4 is exactly the A4 bound to that A5;
 * - A5's frozen A4 output hash still matches that A4 output hash;
 * - A5 result hashes exist so queued exports can bind to them immutably.
 *
 * Any upstream result-affecting change is expected to stale A4/A5 through the
 * Allocation dependency contract. This keeps A6 safe without re-hashing the
 * complete Allocation input chain for every report/export.
 */
final class AllocationA6ReadinessService
{
    /** @return array<string,mixed> */
    public function inspect(): array
    {
        [$a5, $latestA4, $reason] = $this->resolveCurrentSource();

        return [
            'ready' => $reason === null,
            'reason' => $reason,
            'readiness' => [
                'ready' => $reason === null,
                'mode' => 'A6_DIRECT_SOURCE_CHECK',
                'note' => 'A6 trusts finalized A5 verification and checks only latest A4/A5 publishing authority.',
            ],
            'a5' => $a5,
            'a5_version' => $a5?->version,
            'a4_version' => $latestA4?->version,
            'circular_version' => $a5?->circular_version,
            'a5_finalized_at' => $a5?->finalized_at,
            'a5_candidate_hash' => $a5?->candidate_result_hash,
            'a5_capacity_hash' => $a5?->capacity_result_hash,
        ];
    }

    /**
     * Interactive A6 gate. This is intentionally O(1)-style metadata lookup;
     * it does not walk or hash Registration/Preliminary/Written/Viva/etc.
     */
    public function requireReady(): AllocationA5Run
    {
        return $this->requireCurrentPublishingSource();
    }

    /**
     * Publishing gate retained for compatibility with existing A6 callers.
     *
     * "Strict" in A6 means strict binding to the latest finalized A4/A5 result
     * authority, not re-running the upstream Allocation integrity verifier.
     */
    public function requireReadyStrict(): AllocationA5Run
    {
        return $this->requireCurrentPublishingSource();
    }

    private function requireCurrentPublishingSource(): AllocationA5Run
    {
        [$a5, , $reason] = $this->resolveCurrentSource();

        if ($reason !== null || ! $a5 instanceof AllocationA5Run) {
            throw ValidationException::withMessages([
                'allocation_a6' => $reason ?: 'A6 Reporting & Export is not ready.',
            ]);
        }

        return $a5;
    }

    /**
     * @return array{0:?AllocationA5Run,1:?AllocationA4Run,2:?string}
     */
    private function resolveCurrentSource(): array
    {
        $a5 = AllocationA5Run::query()
            ->with('a4Run')
            ->latest('version')
            ->first();

        if ($a5 === null) {
            return [null, null, 'A5 Final Allocation Validity Check has not been run.'];
        }

        if ($a5->status !== 'finalized' || $a5->finalized_at === null) {
            return [$a5, null, 'A5 must be finalized before Reporting & Export.'];
        }

        if ((bool) $a5->is_stale) {
            return [$a5, null, 'A5 is stale and must be re-run/finalized.'];
        }

        if ((int) $a5->candidate_failed > 0 || (int) $a5->capacity_failed > 0) {
            return [$a5, null, 'A5 is not 100% PASS.'];
        }

        if (! $a5->candidate_result_hash || ! $a5->capacity_result_hash || ! $a5->a4_output_hash) {
            return [$a5, null, 'A5 finalized result hashes are incomplete.'];
        }

        $latestA4 = AllocationA4Run::query()
            ->where('status', 'a4_complete')
            ->where('is_stale', false)
            ->latest('version')
            ->first();

        if ($latestA4 === null) {
            return [$a5, null, 'No current completed A4 Allocation result is available.'];
        }

        if ((int) $a5->allocation_a4_run_id !== (int) $latestA4->id) {
            return [$a5, $latestA4, 'A5 is not bound to the latest current A4 Allocation result.'];
        }

        if ($a5->a4Run === null || (bool) $a5->a4Run->is_stale || $a5->a4Run->status !== 'a4_complete') {
            return [$a5, $latestA4, 'A5 source A4 result is not current.'];
        }

        if (! $latestA4->a4_output_hash || ! hash_equals((string) $a5->a4_output_hash, (string) $latestA4->a4_output_hash)) {
            return [$a5, $latestA4, 'A5 source A4 output hash no longer matches the latest current A4 result.'];
        }

        return [$a5, $latestA4, null];
    }
}
