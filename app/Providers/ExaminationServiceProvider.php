<?php

namespace App\Providers;

use App\Support\Examinations\ExaminationConnectionManager;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;

/**
 * Register examination context and runtime database services.
 */
final class ExaminationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('examinations.php'), 'examinations');

        $this->app->scoped(
            ExaminationContext::class,
            fn ($app): ExaminationContext => new ExaminationContext($app['session.store'])
        );

        $this->app->scoped(
            ExaminationConnectionManager::class,
            fn ($app): ExaminationConnectionManager => new ExaminationConnectionManager(
                $app['db'],
                $app->make(Filesystem::class),
            )
        );
    }
}
