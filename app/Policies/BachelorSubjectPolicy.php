<?php

namespace App\Policies;

use App\Models\BachelorSubject;
use App\Models\User;

/** Authorization rules for central bachelor subject administration. */
final class BachelorSubjectPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->is_active && $u->isAdmin();
    }

    public function view(User $u, BachelorSubject $m): bool
    {
        return $this->viewAny($u);
    }

    public function create(User $u): bool
    {
        return $this->viewAny($u);
    }

    public function update(User $u, BachelorSubject $m): bool
    {
        return $this->viewAny($u);
    }
}
