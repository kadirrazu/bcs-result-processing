<?php

namespace App\Policies;

use App\Models\CadreMaster;
use App\Models\User;

/** Authorization rules for central cadre master administration. */
final class CadreMasterPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->is_active && $u->isAdmin();
    }

    public function view(User $u, CadreMaster $m): bool
    {
        return $this->viewAny($u);
    }

    public function create(User $u): bool
    {
        return $this->viewAny($u);
    }

    public function update(User $u, CadreMaster $m): bool
    {
        return $this->viewAny($u);
    }
}
