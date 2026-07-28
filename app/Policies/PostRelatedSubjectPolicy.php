<?php

namespace App\Policies;

use App\Models\PostRelatedSubject;
use App\Models\User;

/** Authorization rules for central post-related subject administration. */
final class PostRelatedSubjectPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->is_active && $u->isAdmin();
    }

    public function view(User $u, PostRelatedSubject $m): bool
    {
        return $this->viewAny($u);
    }

    public function create(User $u): bool
    {
        return $this->viewAny($u);
    }

    public function update(User $u, PostRelatedSubject $m): bool
    {
        return $this->viewAny($u);
    }
}
