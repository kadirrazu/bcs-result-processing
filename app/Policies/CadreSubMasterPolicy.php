<?php
namespace App\Policies;
use App\Models\CadreSubMaster; use App\Models\User;
final class CadreSubMasterPolicy
{
    public function viewAny(User $u): bool { return $u->is_active && $u->isAdmin(); }
    public function view(User $u, CadreSubMaster $m): bool { return $this->viewAny($u); }
    public function create(User $u): bool { return $this->viewAny($u); }
    public function update(User $u, CadreSubMaster $m): bool { return $this->viewAny($u); }
}
