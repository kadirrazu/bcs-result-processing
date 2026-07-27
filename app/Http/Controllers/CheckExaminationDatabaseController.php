<?php

namespace App\Http\Controllers;

use App\Models\Examination;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Http\RedirectResponse;

/**
 * Run an explicit database health check from the central examination registry.
 */
final class CheckExaminationDatabaseController extends Controller
{
    public function __invoke(
        Examination $examination,
        ExaminationConnectionManager $connections,
    ): RedirectResponse {
        $this->authorize('update', $examination);

        $health = $connections->check($examination);

        $examination->forceFill([
            'database_health_status' => $health->connected ? 'connected' : 'failed',
            'database_checked_at' => now(),
            'database_health_error' => $health->error,
            'database_migration_batch' => $health->migrationBatch,
        ])->save();

        return back()->with(
            $health->connected ? 'success' : 'error',
            $health->connected
                ? "Connected to {$examination->database_name} successfully."
                : "Connection to {$examination->database_name} failed.",
        );
    }
}
