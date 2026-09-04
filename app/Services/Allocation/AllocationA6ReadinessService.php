<?php

namespace App\Services\Allocation;

use App\Models\AllocationA5Run;
use Illuminate\Validation\ValidationException;

/**
 * Final publishing gate for Allocation Reporting & Export (A6).
 *
 * A6 is deliberately read-only. It may only consume a CURRENT, finalized,
 * 100%-passing A5 run whose A4 source is also current. Normal page loads use
 * stored/currentness metadata; expensive full-dataset hash verification is
 * reserved for actual TXT/XLSX/DOCX publishing actions.
 */
final class AllocationA6ReadinessService
{
    public function __construct(private readonly AllocationReadinessService $allocationReadiness) {}

    /** @return array<string,mixed> */
    public function inspect(): array
    {
        $dashboard = $this->allocationReadiness->inspectDashboard();
        $a5 = AllocationA5Run::query()->with('a4Run')->latest('version')->first();

        $a5Ready = $a5 !== null
            && $a5->status === 'finalized'
            && ! (bool) $a5->is_stale
            && (int) $a5->candidate_failed === 0
            && (int) $a5->capacity_failed === 0
            && $a5->finalized_at !== null
            && $a5->a4Run !== null
            && $a5->a4Run->status === 'a4_complete'
            && ! (bool) $a5->a4Run->is_stale;

        $ready = (bool) ($dashboard['ready'] ?? false) && $a5Ready;
        $reason = null;
        if (! (bool) ($dashboard['ready'] ?? false)) {
            $reason = 'Allocation upstream readiness/integrity is not current.';
        } elseif ($a5 === null) {
            $reason = 'A5 Final Allocation Validity Check has not been run.';
        } elseif ($a5->status !== 'finalized') {
            $reason = 'A5 must be finalized before Reporting & Export.';
        } elseif ((bool) $a5->is_stale) {
            $reason = 'A5 is stale and must be re-run/finalized.';
        } elseif ((int) $a5->candidate_failed > 0 || (int) $a5->capacity_failed > 0) {
            $reason = 'A5 is not 100% PASS.';
        } elseif ($a5->a4Run === null || $a5->a4Run->status !== 'a4_complete' || (bool) $a5->a4Run->is_stale) {
            $reason = 'A5 source A4 result is not current.';
        }

        return [
            'ready' => $ready,
            'reason' => $reason,
            'readiness' => $dashboard,
            'a5' => $a5,
            'a5_version' => $a5?->version,
            'a4_version' => $a5?->a4Run?->version,
            'circular_version' => $a5?->circular_version,
            'a5_finalized_at' => $a5?->finalized_at,
            'a5_candidate_hash' => $a5?->candidate_result_hash,
            'a5_capacity_hash' => $a5?->capacity_result_hash,
        ];
    }

    /**
     * Lightweight read gate used by interactive A6 reporting pages.
     *
     * Important: page rendering must not re-hash all finalized upstream datasets.
     * Those hashes can span thousands of rows and are intentionally reserved for
     * publishing/export actions where an exact integrity check is required.
     */
    public function requireReady(): AllocationA5Run
    {
        $gate = $this->inspect();
        if (! $gate['ready'] || ! $gate['a5'] instanceof AllocationA5Run) {
            throw ValidationException::withMessages([
                'allocation_a6' => (string) ($gate['reason'] ?: 'A6 Reporting & Export is not ready.'),
            ]);
        }
        return $gate['a5'];
    }

    /**
     * Strict publishing gate. This is deliberately expensive and therefore only
     * runs immediately before generating TXT/XLSX/DOCX output, never on normal
     * page loads.
     */
    public function requireReadyStrict(): AllocationA5Run
    {
        $strict = $this->allocationReadiness->inspectStrict();
        if (! (bool) ($strict['ready'] ?? false)) {
            throw ValidationException::withMessages([
                'allocation_a6' => 'Allocation upstream readiness/integrity is not current.',
            ]);
        }

        $a5 = AllocationA5Run::query()->with('a4Run')->latest('version')->first();
        $a5Ready = $a5 !== null
            && $a5->status === 'finalized'
            && ! (bool) $a5->is_stale
            && (int) $a5->candidate_failed === 0
            && (int) $a5->capacity_failed === 0
            && $a5->finalized_at !== null
            && $a5->a4Run !== null
            && $a5->a4Run->status === 'a4_complete'
            && ! (bool) $a5->a4Run->is_stale;

        if (! $a5Ready) {
            throw ValidationException::withMessages([
                'allocation_a6' => 'A5 must be current, finalized, and 100% PASS before Reporting & Export.',
            ]);
        }

        return $a5;
    }
}
