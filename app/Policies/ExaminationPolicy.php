<?php

namespace App\Policies;

use App\Models\Examination;
use App\Models\User;

/**
 * Authorization rules for central examination registry administration.
 */
final class ExaminationPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    public function view(User $actor, Examination $examination): bool
    {
        return $this->viewAny($actor);
    }

    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    public function update(User $actor, Examination $examination): bool
    {
        return $this->viewAny($actor);
    }
}
