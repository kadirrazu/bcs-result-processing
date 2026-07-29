<?php

namespace App\Providers;

use App\Listeners\RecordSuccessfulLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/** Register application-wide services and event listeners. */
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Service bindings will be added here as application modules mature.
    }

    public function boot(): void
    {
        Event::listen(Login::class, RecordSuccessfulLogin::class);
        Paginator::defaultView('vendor.pagination.tabler');
        Paginator::defaultSimpleView('vendor.pagination.tabler');
    }
}
