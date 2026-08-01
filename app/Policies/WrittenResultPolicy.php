<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WrittenResult;

final class WrittenResultPolicy
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
