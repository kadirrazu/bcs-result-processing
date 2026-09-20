<?php

namespace App\Http\Middleware;

use App\Support\Examinations\ExaminationConnectionManager;
use App\Support\Examinations\ExaminationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Configure the examination connection when a selection exists, while still
 * allowing global pages (for example /dashboard) to work with no selection.
 */
final class ConfigureSelectedExaminationConnection
{
    public function __construct(
        private readonly ExaminationContext $context,
        private readonly ExaminationConnectionManager $connections,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $examination = $this->context->current();

        if ($examination === null) {
            return $next($request);
        }

        $this->connections->configure($examination);

        try {
            return $next($request);
        } finally {
            $this->connections->disconnect();
        }
    }
}
