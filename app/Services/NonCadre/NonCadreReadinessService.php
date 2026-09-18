<?php

namespace App\Services\NonCadre;

use App\Services\Allocation\AllocationA6ReadinessService;
use Illuminate\Validation\ValidationException;

/**
 * Read-only upstream gate for the isolated Non-Cadre module.
 * This service never mutates Cadre Allocation state.
 */
final class NonCadreReadinessService
{
    public function __construct(private readonly AllocationA6ReadinessService $allocationReadiness) {}

    /** @return array<string,mixed> */
    public function inspect(): array
    {
        $allocation = $this->allocationReadiness->inspect();
        $ready = (bool) ($allocation['ready'] ?? false);

        return [
            'ready' => $ready,
            'reason' => $ready ? null : ($allocation['reason'] ?? 'Finalize the current Cadre Allocation before starting Non-Cadre Processing.'),
            'allocation_a5_version' => $allocation['a5_version'] ?? null,
            'allocation_a4_version' => $allocation['a4_version'] ?? null,
            'allocation_candidate_hash' => $allocation['a5_candidate_hash'] ?? null,
        ];
    }

    public function requireReady(): void
    {
        $gate = $this->inspect();
        if (! $gate['ready']) {
            throw ValidationException::withMessages([
                'non_cadre' => (string) $gate['reason'],
            ]);
        }
    }
}
