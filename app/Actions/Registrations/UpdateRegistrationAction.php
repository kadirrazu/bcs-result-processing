<?php

namespace App\Actions\Registrations;

use App\Models\Registration;
use App\Services\Registrations\RegistrationQuotaResolver;

/** Update a manually maintained registration and recalculate derived fields. */
final class UpdateRegistrationAction
{
    public function __construct(private readonly RegistrationQuotaResolver $quotaResolver) {}

    /** @param array<string, mixed> $attributes */
    public function execute(Registration $registration, array $attributes): Registration
    {
        $attributes['has_quota'] = $this->quotaResolver->hasQuota(
            $attributes['has_ff_quota'] ?? null,
            $attributes['has_em_quota'] ?? null,
            $attributes['has_phc_quota'] ?? null,
        );
        $attributes['validation_status'] = 'valid';

        $registration->update($attributes);

        return $registration->refresh();
    }
}
