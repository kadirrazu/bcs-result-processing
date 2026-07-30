<?php

namespace App\Policies;

use App\Models\Division;
use App\Models\User;

/** Authorization rules for central division master administration. */
final class DivisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->isAdmin();
    }

    public function view(User $user, Division $division): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Division $division): bool
    {
        return $this->viewAny($user);
    }
}
