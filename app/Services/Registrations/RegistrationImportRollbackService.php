<?php

namespace App\Services\Registrations;

use App\Models\RegistrationImportBatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Reverse one completed import batch using its row-level audit snapshots. */
final class RegistrationImportRollbackService
{
    public function rollback(RegistrationImportBatch $batch, int $userId, ?string $reason = null): RegistrationImportBatch
    {
        if ($batch->rolled_back_at !== null) {
            throw new RuntimeException('This import batch has already been rolled back.');
        }
        if (! in_array($batch->status, ['completed', 'completed_with_errors'], true)) {
            throw new RuntimeException('Only completed import batches can be rolled back.');
        }

        DB::connection('exam')->transaction(function () use ($batch, $userId, $reason): void {
            $batch->rows()->whereIn('action', ['inserted', 'updated'])->chunkByIdDesc(
                500,
                function ($rows): void {
                    foreach ($rows as $row) {
                        if ($row->action === 'inserted') {
                            // Delete only when the candidate still belongs to this import batch.
                            DB::connection('exam')->table('registrations')
                                ->where('id', $row->registration_id)
                                ->where('source_batch_id', $row->batch_id)
                                ->delete();
                            continue;
                        }

                        $before = $row->before_data;
                        if (! is_array($before) || ! isset($before['id'])) {
                            throw new RuntimeException("Missing rollback snapshot for import row {$row->source_row}.");
                        }

                        $id = $before['id'];
                        unset($before['id']);
                        DB::connection('exam')->table('registrations')->where('id', $id)->update($before);
                    }
                }
            );

            $batch->update([
                'status' => 'rolled_back',
                'rolled_back_at' => now(),
                'rolled_back_by' => $userId,
                'rollback_reason' => $reason,
            ]);
        });

        return $batch->refresh();
    }
}
