<?php

namespace App\Services\Viva;

use App\Models\VivaCandidateMapping;
use App\Models\VivaImportBatch;
use App\Models\VivaProcessingState;
use App\Models\WrittenResult;
use Illuminate\Support\Facades\DB;
use Throwable;

final class VivaBoardApprovalService
{
    private const APPROVAL_CHUNK_SIZE = 1500;

    public function approve(int $id, int $actor): VivaImportBatch
    {
        $batch = VivaImportBatch::query()
            ->where('import_type', 'board')
            ->findOrFail($id);

        $batch->update([
            'status' => 'approving',
            'progress_percent' => 0,
            'failure_message' => null,
        ]);

        try {
            $query = DB::connection('exam')
                ->table('viva_board_import_staging')
                ->where('batch_id', $id)
                ->whereIn('validation_status', ['valid', 'warning']);

            $total = (int) (clone $query)->count();
            $denominator = max(1, $total);

            $done = 0;
            $inserted = 0;
            $updated = 0;

            $query
                ->orderBy('id')
                ->chunkById(self::APPROVAL_CHUNK_SIZE, function ($rows) use (
                    $batch,
                    $denominator,
                    &$done,
                    &$inserted,
                    &$updated
                ): void {
                    $mappingIds = $rows
                        ->pluck('viva_candidate_mapping_id')
                        ->filter()
                        ->unique()
                        ->values();

                    if ($mappingIds->isEmpty()) {
                        return;
                    }

                    $mappings = VivaCandidateMapping::query()
                        ->whereIn('id', $mappingIds)
                        ->get()
                        ->keyBy('id');

                    $writtenIds = $mappings
                        ->pluck('written_result_id')
                        ->filter()
                        ->unique()
                        ->values();

                    $written = WrittenResult::query()
                        ->whereIn('id', $writtenIds)
                        ->get(['id', 'cadre_category', 'written_qualified_track'])
                        ->keyBy('id');

                    $existingMappingIds = DB::connection('exam')
                        ->table('viva_results')
                        ->whereIn('viva_candidate_mapping_id', $mappingIds)
                        ->pluck('viva_candidate_mapping_id')
                        ->map(static fn ($value) => (int) $value)
                        ->flip();

                    $now = now();
                    $payload = [];
                    $chunkInserted = 0;
                    $chunkUpdated = 0;

                    foreach ($rows as $row) {
                        $mapping = $mappings->get($row->viva_candidate_mapping_id);
                        $writtenResult = $mapping
                            ? $written->get($mapping->written_result_id)
                            : null;

                        if (! $mapping || ! $writtenResult) {
                            continue;
                        }

                        $mappingId = (int) $mapping->id;

                        if ($existingMappingIds->has($mappingId)) {
                            $chunkUpdated++;
                        } else {
                            $chunkInserted++;
                        }

                        $payload[] = [
                            'viva_candidate_mapping_id' => $mappingId,
                            'registration_id' => $mapping->registration_id,
                            'written_result_id' => $mapping->written_result_id,
                            'user_id' => $mapping->user_id,
                            'reg' => $mapping->reg,
                            'code' => $mapping->code,
                            'cadre_category' => $writtenResult->cadre_category,
                            'written_qualified_track' => $writtenResult->written_qualified_track,
                            'raw_viva_date' => $row->raw_viva_date,
                            'viva_date' => $row->viva_date,
                            'member_id' => $row->member_id,
                            'raw_mark' => $row->raw_mark,
                            'mark' => $row->mark,
                            'attendance_status' => $row->attendance_status,
                            'raw_viva_cff' => $row->raw_viva_cff,
                            'raw_viva_em' => $row->raw_viva_em,
                            'raw_viva_phc' => $row->raw_viva_phc,
                            'viva_cff' => (bool) $row->viva_cff,
                            'viva_em' => (bool) $row->viva_em,
                            'viva_phc' => (bool) $row->viva_phc,
                            'raw_invalid_flag' => $row->raw_invalid_flag,
                            'raw_issue_flag' => $row->raw_issue_flag,
                            'invalid_flag' => (bool) $row->invalid_flag,
                            'issue_flag' => (bool) $row->issue_flag,
                            'validation_status' => $row->validation_status,
                            'viva_result_status' => 'pending',
                            'source_batch_id' => $batch->id,
                            'finalized_at' => null,
                            'status' => 'active',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($payload === []) {
                        return;
                    }

                    DB::connection('exam')
                        ->table('viva_results')
                        ->upsert(
                            $payload,
                            ['viva_candidate_mapping_id'],
                            [
                                'registration_id',
                                'written_result_id',
                                'user_id',
                                'reg',
                                'code',
                                'cadre_category',
                                'written_qualified_track',
                                'raw_viva_date',
                                'viva_date',
                                'member_id',
                                'raw_mark',
                                'mark',
                                'attendance_status',
                                'raw_viva_cff',
                                'raw_viva_em',
                                'raw_viva_phc',
                                'viva_cff',
                                'viva_em',
                                'viva_phc',
                                'raw_invalid_flag',
                                'raw_issue_flag',
                                'invalid_flag',
                                'issue_flag',
                                'validation_status',
                                'viva_result_status',
                                'source_batch_id',
                                'finalized_at',
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
                        'progress_percent' => min(
                            99.9,
                            round(($done / $denominator) * 100, 4)
                        ),
                    ]);
                }, 'id');

            $batch->update([
                'status' => 'approved',
                'approved_by' => $actor,
                'approved_at' => now(),
                'finished_at' => now(),
                'progress_percent' => 100,
            ]);

            VivaProcessingState::query()->updateOrCreate(
                ['id' => 1],
                [
                    'status' => 'board_data_imported',
                    'latest_board_batch_id' => $batch->id,
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
