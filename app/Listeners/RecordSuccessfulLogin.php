<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * Record the most recent successful interactive login for an application user.
 */
class RecordSuccessfulLogin
{
    /**
     * Handle the authentication event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $event->user->forceFill([
            'last_login_at' => now(),
        ])->saveQuietly();
    }
}
