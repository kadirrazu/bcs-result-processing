<?php
namespace App\Policies;
use App\Models\MeritResult;use App\Models\User;
final class MeritResultPolicy
{
 public function viewAny(User $user):bool{return $user->is_active;}
 public function view(User $user,MeritResult $result):bool{return $user->is_active;}
 public function process(User $user):bool{return $user->is_active&&($user->isAdmin()||$user->role->value==='operator');}
}
