<?php

namespace App\Queries\Examinations;

use App\Models\Examination;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Build the searchable examination registry listing.
 */
final class ListExaminationsQuery
{
    /** @return LengthAwarePaginator<int, Examination> */
    public function execute(string $search = ''): LengthAwarePaginator
    {
        return Examination::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('database_name', 'like', "%{$search}%")
                        ->orWhere('bcs_number', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('bcs_number')
            ->paginate(15)
            ->withQueryString();
    }
}
