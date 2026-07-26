<?php

namespace App\Actions\Users;

use App\Data\UserData;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Update a central application user while enforcing application invariants.
 */
final class UpdateUserAction
{
    /**
     * Update the target user.
     *
     * The authenticated actor cannot deactivate their own account because that
     * could remove the last usable administrative session during maintenance.
     *
     * @throws ValidationException
     */
    public function execute(User $actor, User $target, UserData $data): User
    {
        if ($actor->is($target) && ! $data->isActive) {
            throw ValidationException::withMessages([
                'is_active' => 'You cannot deactivate your own account.',
            ]);
        }

        return DB::transaction(function () use ($target, $data): User {
            $target->update($data->toAttributes());

            return $target->refresh();
        });
    }
}
