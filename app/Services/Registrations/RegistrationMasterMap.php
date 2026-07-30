<?php

namespace App\Services\Registrations;

use App\Models\{BachelorSubject, District, Division, Gender, PostRelatedSubject, University};

/** Load central master codes once so large imports never perform per-row SQL lookups. */
final class RegistrationMasterMap
{
    /** @return array<string, array<string, bool>> */
    public function load(): array
    {
        $districts = District::query()->where('is_active', true)->get(['code', 'division_code']);

        return [
            'sex' => $this->map(Gender::query()->where('is_active', true)->pluck('code')->all()),
            'district' => $this->map($districts->pluck('code')->all()),
            'district_division' => $districts->mapWithKeys(static fn (District $district): array => [(string) $district->code => (int) $district->division_code])->all(),
            'division' => $this->map(Division::query()->where('is_active', true)->pluck('code')->all()),
            'university' => $this->map(University::query()->where('is_active', true)->pluck('code')->all()),
            'b_subject' => $this->map(BachelorSubject::query()->where('is_active', true)->pluck('subject_code')->all()),
            'post_related_subject' => $this->map(PostRelatedSubject::query()->where('is_active', true)->pluck('subject_code')->all()),
        ];
    }

    /** @param list<mixed> $values @return array<string, bool> */
    private function map(array $values): array
    {
        return array_fill_keys(array_map('strval', $values), true);
    }
}
