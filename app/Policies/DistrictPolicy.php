<?php

namespace App\Policies;

use App\Models\District;
use App\Models\User;

/** Authorization rules for central district master administration. */
final class DistrictPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function view(User $user, District $district): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, District $district): bool
    {
        return $this->viewAny($user);
    }
}
