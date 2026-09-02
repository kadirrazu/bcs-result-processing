<?php

namespace App\Queries\Registrations;

use App\Models\Registration;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Build an indexed, server-side registration listing for large examinations. */
final class ListRegistrationsQuery
{
    /** @param array<string, mixed> $filters */
    public function execute(array $filters, int $perPage): LengthAwarePaginator
    {
        return Registration::query()
            ->select([
                'id', 'reg', 'user_id', 'name', 'father_name', 'cadre_category', 'sex_code',
                'district_code', 'division_code', 'bachelor_subject_code',
                'has_quota', 'status', 'validation_status',
            ])
            ->filtered($filters)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
