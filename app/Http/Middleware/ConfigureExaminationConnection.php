<?php

namespace App\Http\Middleware;

use App\Support\Examinations\ExaminationConnectionManager;
use App\Support\Examinations\ExaminationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Configure the shared runtime connection for the active examination request.
 */
final class ConfigureExaminationConnection
{
    public function __construct(
        private readonly ExaminationContext $context,
        private readonly ExaminationConnectionManager $connections,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $examination = $this->context->current();
        abort_if($examination === null, 409, 'No active examination context is available.');

        $this->connections->configure($examination);

        try {
            return $next($request);
        } finally {
            // Long-running workers must never leak one user's BCS connection.
            $this->connections->disconnect();
        }
    }
}
