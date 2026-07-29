<?php
namespace App\Policies;
use App\Models\Registration;
use App\Models\User;
/** Registration permissions: viewers can read; administrators and operators may change/import. */
final class RegistrationPolicy
{
 public function viewAny(User $u): bool{return $u->is_active;}
 public function view(User $u, Registration $m): bool{return $u->is_active;}
 public function create(User $u): bool{return $u->is_active && ($u->isAdmin() || $u->role->value === 'operator');}
 public function update(User $u, Registration $m): bool{return $this->create($u);}
 public function import(User $u): bool{return $this->create($u);}
}
