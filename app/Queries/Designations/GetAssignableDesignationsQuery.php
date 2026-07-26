<?php

namespace App\Queries\Designations;

use App\Models\Designation;
use Illuminate\Database\Eloquent\Collection;

class GetAssignableDesignationsQuery
{
    /**
     * Retrieve active designations available for assignment.
     *
     * When editing a user, the user's currently assigned designation remains
     * available even if that designation has subsequently been deactivated.
     *
     * @return Collection<int, Designation>
     */
    public function execute(?int $currentDesignationId = null): Collection
    {
        return Designation::query()
            ->where(function ($query) use ($currentDesignationId): void {
                $query->where('is_active', true);

                if ($currentDesignationId !== null) {
                    $query->orWhere('id', $currentDesignationId);
                }
            })
            ->orderBy('name')
            ->get();
    }
}
