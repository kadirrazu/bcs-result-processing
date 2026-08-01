<?php

namespace App\Policies;

use App\Models\PreliminaryResult;
use App\Models\User;

final class PreliminaryResultPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function process(User $user): bool
    {
        return $user->is_active && ($user->isAdmin() || $user->role->value === 'operator');
    }
}
