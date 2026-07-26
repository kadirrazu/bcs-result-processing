<?php

namespace App\Queries\Designations;

use App\Models\Designation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Retrieve designations that may be assigned from a user form.
 */
final class GetAssignableDesignationsQuery
{
    /**
     * Include an inactive current designation so an existing user remains
     * editable without silently forcing an unrelated designation change.
     *
     * @return Collection<int, Designation>
     */
    public function execute(?User $user = null): Collection
    {
        return Designation::query()
            ->where(function ($query) use ($user): void {
                $query->where('is_active', true);

                if ($user?->designation_id !== null) {
                    $query->orWhereKey($user->designation_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
