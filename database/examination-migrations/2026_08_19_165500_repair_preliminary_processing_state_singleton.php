<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::connection('exam')->hasTable('preliminary_processing_states')) {
            return;
        }

        DB::connection('exam')->transaction(function (): void {
            $query = DB::connection('exam')->table('preliminary_processing_states');

            $rows = $query->orderByDesc('updated_at')->get();

            if ($rows->isEmpty()) {
                return;
            }

            /*
             * The historical model accidentally allowed id=2/3/... singleton
             * rows after DELETE-based module reset because id=1 was not
             * mass-assignable. Prefer the most recently updated state that is
             * actually attached to an approved/imported Preliminary snapshot.
             * A later controller-created NOT_STARTED duplicate must not outrank
             * the real MARK_IMPORTED state.
             */
            $canonical = $rows
                ->filter(static fn ($row): bool => $row->latest_import_batch_id !== null)
                ->sortByDesc(static fn ($row): string => (string) ($row->updated_at ?? ''))
                ->first()
                ?? $rows->first();

            $payload = (array) $canonical;
            $payload['id'] = 1;

            // Replace every accidental duplicate with the single canonical row.
            $query->delete();
            DB::connection('exam')
                ->table('preliminary_processing_states')
                ->insert($payload);
        }, 3);
    }

    public function down(): void
    {
        // Data-repair migration: deliberately non-reversible.
    }
};
