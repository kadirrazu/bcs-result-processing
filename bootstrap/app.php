<?php

use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
        |--------------------------------------------------------------------------
        | Dynamic examination database middleware priority
        |--------------------------------------------------------------------------
        |
        | Registration route model binding and FormRequest validation may query the
        | examination database before a controller method executes. Therefore the
        | active examination must be resolved and the runtime `exam` connection must
        | be configured immediately after the web session starts, but before Laravel
        | performs route bindings or validation-related database queries.
        |
        */
        $middleware->appendToPriorityList(
            StartSession::class,
            EnsureExaminationSelected::class,
        );

        $middleware->appendToPriorityList(
            EnsureExaminationSelected::class,
            ConfigureExaminationConnection::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
