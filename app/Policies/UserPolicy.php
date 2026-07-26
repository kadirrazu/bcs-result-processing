<?php

namespace App\Policies;

use App\Models\User;

/**
 * Authorization rules for central application user administration.
 */
class UserPolicy
{
    /**
     * Allow administrators to view the user directory.
     */
    public function viewAny(User $actor): bool
    {
        return $actor->is_active && $actor->isAdmin();
    }

    /**
     * Allow administrators to view an individual user.
     */
    public function view(User $actor, User $user): bool
    {
        return $this->viewAny($actor);
    }

    /**
     * Allow administrators to create users.
     */
    public function create(User $actor): bool
    {
        return $this->viewAny($actor);
    }

    /**
     * Allow administrators to update users.
     */
    public function update(User $actor, User $user): bool
    {
        return $this->viewAny($actor);
    }
}
