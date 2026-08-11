<?php

namespace App\Policies;

use App\Models\ChoiceSource;
use App\Models\User;

final class ChoiceSourcePolicy
{
    public function viewAny(User $user): bool { return $user->is_active; }
    public function process(User $user): bool { return $user->is_active && ($user->isAdmin() || $user->role->value === 'operator'); }
}
