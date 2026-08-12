<?php
namespace App\Policies;
use App\Models\TabulationResult;use App\Models\User;
final class TabulationResultPolicy
{
    public function viewAny(User $user):bool{return $user->is_active;}
    public function view(User $user,TabulationResult $result):bool{return $user->is_active;}
    public function process(User $user):bool{return $user->is_active&&($user->isAdmin()||$user->role->value==='operator');}
}
