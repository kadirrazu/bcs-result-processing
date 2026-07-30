<?php

namespace App\Actions\Registrations;

use App\Models\Registration;
use App\Services\Registrations\RegistrationBusinessRuleNormalizer;
use App\Services\Registrations\RegistrationQuotaResolver;

/** Create one manually entered registration using the shared domain rules. */
final class CreateRegistrationAction
{
    public function __construct(
        private readonly RegistrationQuotaResolver $quotaResolver,
        private readonly RegistrationBusinessRuleNormalizer $businessRules,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(array $attributes): Registration
    {
        $attributes['has_quota'] = $this->quotaResolver->hasQuota(
            $attributes['has_ff_quota'] ?? null,
            $attributes['has_em_quota'] ?? null,
            $attributes['has_phc_quota'] ?? null,
        );
        $attributes['validation_status'] = 'valid';
        $attributes = $this->businessRules->normalize($attributes)['attributes'];

        return Registration::query()->create($attributes);
    }
}
