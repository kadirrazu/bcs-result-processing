<?php

namespace App\Http\Middleware;

use App\Support\Examinations\ExaminationContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent examination-domain screens from running without a selected BCS.
 */
final class EnsureExaminationSelected
{
    public function handle(Request $request, Closure $next): Response|RedirectResponse
    {
        if (! app(ExaminationContext::class)->hasActive()) {
            return redirect()
                ->route('examinations.index')
                ->with('error', 'Select an examination before accessing examination data.');
        }

        return $next($request);
    }
}
