<?php

namespace App\Policies;

use App\Models\University;
use App\Models\User;

/** Authorization rules for central university master administration. */
final class UniversityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function view(User $user, University $university): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, University $university): bool
    {
        return $this->viewAny($user);
    }
}
