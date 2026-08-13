<?php

namespace App\Services\Registrations;

use App\Enums\CadreCategory;
use App\Enums\RegistrationStatus;

/** Validate identity, blocking business rules and required central master codes before persistence. */
final class RegistrationRowValidator
{
    /** @param array<string, mixed> $registration @param array<string, array<string, bool>> $masters @return list<string> */
    public function validate(array $registration, array $masters): array
    {
        $errors = [];

        if (! preg_match('/^[A-Za-z0-9]{1,10}$/', (string) $registration['user_id'])) {
            $errors[] = 'USER must be alphanumeric and at most 10 characters.';
        }
        if (! preg_match('/^\d{1,8}$/', (string) $registration['reg'])) {
            $errors[] = 'REG must contain at most 8 digits.';
        }
        if ($registration['name'] === '') {
            $errors[] = 'NAME is required.';
        }
        if (! in_array($registration['cadre_category'], CadreCategory::values(), true)) {
            $errors[] = 'CADRE_CATEGORY must be 1, 2 or 3.';
        }
        if (! in_array($registration['status'], RegistrationStatus::values(), true)) {
            $errors[] = 'STATUS must be active, cancelled or withheld.';
        }

        foreach ([
            'ssc_year' => 'SSC_YEAR',
            'hsc_year' => 'HSC_YEAR',
            'graduation_year' => 'GRADUATION_YEAR',
        ] as $field => $label) {
            $year = $registration[$field] ?? null;
            if ($year !== null && ($year < 1900 || $year > ((int) date('Y') + 1))) {
                $errors[] = "{$label} must be a four-digit year between 1900 and next calendar year.";
            }
        }

        foreach ([
            'sex_code' => 'sex',
            'district_code' => 'district',
            'division_code' => 'division',
            'bachelor_subject_code' => 'b_subject',
            'post_related_subject_code' => 'post_related_subject',
        ] as $field => $map) {
            if ($registration[$field] !== null && ! isset($masters[$map][(string) $registration[$field]])) {
                $errors[] = "Unknown {$map} code [{$registration[$field]}].";
            }
        }

        if (in_array($registration['cadre_category'], [CadreCategory::Technical->value, CadreCategory::GeneralAndTechnical->value], true)
            && $registration['post_related_subject_code'] === null) {
            $errors[] = 'POST_RELATED_SUBJECT is required for TT and GT candidates.';
        }

        return $errors;
    }
}
