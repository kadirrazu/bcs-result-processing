<?php

namespace App\Http\Middleware;

use App\Support\Examinations\ExaminationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks unsafe processing mutations while an examination is administratively completed. */
final class EnsureExaminationProcessingOpen
{
    public function __construct(private readonly ExaminationContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $routeName = (string) ($request->route()?->getName() ?? '');
        if (str_starts_with($routeName, 'allocation.a6.') || str_starts_with($routeName, 'examination-reports.')) {
            return $next($request);
        }

        $examination = $this->context->current();
        if ($examination?->is_completed) {
            return redirect()->back()->with('error', 'This BCS is marked as Completed. Unlock it from Examinations before re-processing or changing processing data.');
        }

        return $next($request);
    }
}
