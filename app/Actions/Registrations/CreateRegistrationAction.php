<?php

namespace App\Actions\Registrations;

use App\Models\Registration;
use App\Services\Registrations\RegistrationQuotaResolver;

/**
 * Create one manually entered registration in the active examination database.
 *
 * Keeping the derived quota calculation here prevents controllers, imports and
 * future APIs from implementing the same business rule differently.
 */
final class CreateRegistrationAction
{
    public function __construct(private readonly RegistrationQuotaResolver $quotaResolver) {}

    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes): Registration
    {
        $attributes['has_quota'] = $this->quotaResolver->hasQuota(
            $attributes['has_ff_quota'] ?? null,
            $attributes['has_em_quota'] ?? null,
            $attributes['has_phc_quota'] ?? null,
        );

        // A manually saved record has already passed the same rules used by the form request.
        $attributes['validation_status'] = 'valid';

        return Registration::query()->create($attributes);
    }
}
