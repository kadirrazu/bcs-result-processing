<?php

namespace App\Services\Reporting;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Shared age calculation against the examination's configured reference date. */
final class CandidateAgeCalculator
{
    /** @return array{years:int,months:int,days:int,label:string} */
    public function calculate(string|CarbonImmutable $dateOfBirth, string|CarbonImmutable $referenceDate): array
    {
        $dob = $dateOfBirth instanceof CarbonImmutable ? $dateOfBirth->startOfDay() : CarbonImmutable::parse($dateOfBirth)->startOfDay();
        $reference = $referenceDate instanceof CarbonImmutable ? $referenceDate->startOfDay() : CarbonImmutable::parse($referenceDate)->startOfDay();

        if ($dob->greaterThan($reference)) {
            throw new InvalidArgumentException('Date of birth cannot be after the age calculation date.');
        }

        $diff = $dob->diff($reference);

        return [
            'years' => $diff->y,
            'months' => $diff->m,
            'days' => $diff->d,
            'label' => sprintf('%d Years %d Months %d Days', $diff->y, $diff->m, $diff->d),
        ];
    }
}
