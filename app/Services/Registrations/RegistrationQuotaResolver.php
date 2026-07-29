<?php

namespace App\Services\Registrations;

/**
 * Centralize the derived quota rule used by manual entry and Excel imports.
 *
 * Raw quota columns intentionally retain their imported numeric values. The
 * derived boolean exists only to make high-volume quota filtering inexpensive.
 */
final class RegistrationQuotaResolver
{
    /**
     * Determine whether the candidate has any qualifying quota declaration.
     */
    public function hasQuota(?int $ffCode, ?int $emCode, ?int $phcCode): bool
    {
        return $ffCode === 2 || $emCode !== null || $phcCode !== null;
    }
}
