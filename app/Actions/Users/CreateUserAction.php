<?php

namespace App\Actions\Users;

use App\Data\UserData;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Create a central application user as one atomic operation.
 */
final class CreateUserAction
{
    public function execute(UserData $data): User
    {
        return DB::transaction(
            fn (): User => User::query()->create($data->toAttributes())
        );
    }
}
