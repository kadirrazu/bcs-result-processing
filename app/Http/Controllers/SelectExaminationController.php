<?php

namespace App\Http\Controllers;

use App\Models\Examination;
use App\Support\Examinations\ExaminationConnectionManager;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Select the active examination only after database connectivity succeeds.
 */
final class SelectExaminationController extends Controller
{
    public function __invoke(
        Request $request,
        Examination $examination,
        ExaminationContext $context,
        ExaminationConnectionManager $connections,
    ): RedirectResponse {
        abort_unless($request->user()?->is_active, 403);
        abort_unless($examination->isSelectable(), 422, 'This examination cannot be selected.');

        $health = $connections->check($examination);

        if (! $health->connected) {
            return back()->withErrors([
                'database' => "Could not connect to {$examination->database_name}. Check that the database exists and the credentials have access.",
            ]);
        }

        $context->select($examination);

        return back()->with('success', "{$examination->name} is now the active examination.");
    }
}
