<?php

namespace App\Services\Viva;

use App\Enums\VivaProcessingStatus;
use App\Models\VivaImportBatch;
use App\Models\VivaProcessingState;
use Illuminate\Support\Facades\DB;
use Throwable;

final class VivaMappingApprovalService
{
    public function approve(int $batchId, int $actorId): VivaImportBatch
    {
        $batch = VivaImportBatch::query()
            ->where('import_type', 'mapping')
            ->findOrFail($batchId);

        $batch->update([
            'status' => 'approving',
            'processed_rows' => 0,
            'approved_rows' => 0,
            'inserted_rows' => 0,
            'updated_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'finished_at' => null,
        ]);

        try {
            $query = DB::connection('exam')
                ->table('viva_mapping_import_staging')
                ->where('batch_id', $batchId)
                ->where('validation_status', 'valid');

            $eligible = (int) (clone $query)->count();
            $denominator = max(1, $eligible);
            $chunkSize = max(100, (int) config('viva.mapping_merge_chunk_size', 1500));

            $done = 0;
            $inserted = 0;
            $updated = 0;

            $query
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (
                    $batch,
                    $batchId,
                    $denominator,
                    &$done,
                    &$inserted,
                    &$updated
                ): void {
                    $registrationIds = $rows
                        ->pluck('registration_id')
                        ->filter()
                        ->map(static fn ($value) => (int) $value)
                        ->unique()
                        ->values();

                    if ($registrationIds->isEmpty()) {
                        return;
                    }

                    // One lookup per chunk replaces the former per-candidate SELECT.
                    // This is also used to preserve exact inserted/updated counters.
                    $existingRegistrationIds = DB::connection('exam')
                        ->table('viva_candidate_mappings')
                        ->whereIn('registration_id', $registrationIds)
                        ->pluck('registration_id')
                        ->map(static fn ($value) => (int) $value)
                        ->flip();

                    $timestamp = now()->format('Y-m-d H:i:s');
                    $payload = [];
                    $chunkInserted = 0;
                    $chunkUpdated = 0;

                    foreach ($rows as $row) {
                        if ($row->registration_id === null) {
                            continue;
                        }

                        $registrationId = (int) $row->registration_id;
                        if ($existingRegistrationIds->has($registrationId)) {
                            $chunkUpdated++;
                        } else {
                            $chunkInserted++;
                        }

                        $payload[] = [
                            'registration_id' => $registrationId,
                            'written_result_id' => $row->written_result_id,
                            'user_id' => $row->user_id,
                            'reg' => $row->reg,
                            'code' => $row->code,
                            'source_batch_id' => $batchId,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }

                    if ($payload === []) {
                        return;
                    }

                    // One bulk write per chunk replaces the former per-candidate
                    // INSERT/UPDATE loop. Validation already guarantees the mapping
                    // uniqueness contract before approval reaches this service.
                    DB::connection('exam')
                        ->table('viva_candidate_mappings')
                        ->upsert(
                            $payload,
                            ['registration_id'],
                            [
                                'written_result_id',
                                'user_id',
                                'reg',
                                'code',
                                'source_batch_id',
                                'updated_at',
                            ]
                        );

                    $chunkProcessed = count($payload);
                    $done += $chunkProcessed;
                    $inserted += $chunkInserted;
                    $updated += $chunkUpdated;

                    $batch->update([
                        'processed_rows' => $done,
                        'approved_rows' => $done,
                        'inserted_rows' => $inserted,
                        'updated_rows' => $updated,
                        'progress_percent' => min(99.9, round(($done / $denominator) * 100, 4)),
                    ]);
                }, 'id');

            $batch->update([
                'status' => 'approved',
                'approved_rows' => $done,
                'processed_rows' => $done,
                'inserted_rows' => $inserted,
                'updated_rows' => $updated,
                'progress_percent' => 100,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'finished_at' => now(),
            ]);

            VivaProcessingState::query()->updateOrCreate(
                ['id' => 1],
                [
                    'status' => VivaProcessingStatus::MappingImported->value,
                    'latest_mapping_batch_id' => $batchId,
                    'is_stale' => false,
                    'stale_reason' => null,
                ]
            );

            return $batch->refresh();
        } catch (Throwable $e) {
            $batch->update([
                'status' => 'failed',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }
}
