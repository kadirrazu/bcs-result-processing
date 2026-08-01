<?php

namespace App\Actions\Registrations;

use App\Models\Registration;
use App\Models\User;
use App\Services\Registrations\RegistrationAuditService;
use App\Services\Registrations\RegistrationBusinessRuleNormalizer;
use App\Services\Registrations\RegistrationQuotaResolver;
use Illuminate\Support\Facades\DB;

/** Update one registration and preserve an immutable before/after audit trail. */
final class UpdateRegistrationAction
{
    public function __construct(
        private readonly RegistrationQuotaResolver $quotaResolver,
        private readonly RegistrationBusinessRuleNormalizer $businessRules,
        private readonly RegistrationAuditService $auditService,
    ) {}

    /** @param array<string, mixed> $attributes */
    public function execute(Registration $registration, array $attributes, User $actor, string $reason): Registration
    {
        $attributes['has_quota'] = $this->quotaResolver->hasQuota(
            $attributes['has_ff_quota'] ?? null,
            $attributes['has_em_quota'] ?? null,
            $attributes['has_phc_quota'] ?? null,
        );
        $attributes['validation_status'] = 'valid';
        $attributes = $this->businessRules->normalize($attributes)['attributes'];

        return DB::connection('exam')->transaction(function () use ($registration, $attributes, $actor, $reason): Registration {
            $before = $this->snapshot($registration);

            $registration->fill($attributes);
            $dirty = $registration->getDirty();

            // Submitting the form without an actual data change must not create a false audit event.
            if ($dirty === []) {
                return $registration->refresh();
            }

            $registration->save();
            $registration->refresh();

            $after = $this->snapshot($registration);
            $changedFields = [];

            foreach (array_keys($dirty) as $field) {
                if ($field === 'updated_at') {
                    continue;
                }

                $changedFields[$field] = [
                    'before' => $before[$field] ?? null,
                    'after' => $after[$field] ?? null,
                ];
            }

            $this->auditService->recordManualUpdate(
                $registration,
                $actor,
                trim($reason),
                $before,
                $after,
                $changedFields,
            );

            return $registration;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(Registration $registration): array
    {
        $snapshot = $registration->getRawOriginal();

        // Database-generated timestamps are operational metadata, not candidate facts.
        unset($snapshot['created_at'], $snapshot['updated_at']);

        return $snapshot;
    }
}
