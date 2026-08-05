<?php

namespace App\Services\Viva;

use App\Models\VivaProcessingRun;
use App\Models\VivaProcessingState;
use App\Models\VivaResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class VivaResultProcessingService
{
    public function __construct(private readonly VivaRuleConfig $rules) {}

    public function process(int $runId, int $actorId): VivaProcessingRun
    {
        /** @var VivaProcessingRun $run */
        $run = VivaProcessingRun::query()->findOrFail($runId);
        $state = VivaProcessingState::query()->firstOrCreate(['id' => 1], ['status' => 'not_started']);

        if (! $state->reconciliation_generated_at || $state->is_stale) {
            throw new RuntimeException('Generate a current Viva reconciliation before processing Viva results.');
        }

        $total = VivaResult::query()->count();
        $run->update([
            'status' => 'running',
            'total_rows' => $total,
            'processed_rows' => 0,
            'progress_percent' => 0,
            'current_step' => 'Evaluating Viva attendance and pass rules',
            'started_at' => now(),
            'failure_message' => null,
        ]);
        $state->update([
            'status' => 'processing_running',
            'latest_processing_run_id' => $run->id,
        ]);

        $counts = [
            'academic_processed_count' => 0,
            'pass_count' => 0,
            'fail_count' => 0,
            'absent_count' => 0,
            'cancelled_count' => 0,
            'withheld_count' => 0,
            'expelled_count' => 0,
        ];

        $processed = 0;
        $chunkSize = max(250, (int) config('viva.processing_chunk_size', 2000));

        try {
            VivaResult::query()
                ->select(['id', 'status', 'attendance_status', 'mark', 'reg', 'code', 'written_qualified_track'])
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use ($run, $actorId, $total, &$processed, &$counts): void {
                    $updates = [];
                    $now = now();

                    foreach ($rows as $row) {
                        $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
                        $attendance = strtolower((string) $row->attendance_status);
                        $resultStatus = 'not_applicable';
                        $failReasons = [];

                        if ($status !== 'active') {
                            $failReasons[] = match ($status) {
                                'cancelled' => 'CANCELLED',
                                'withheld' => 'WITHHELD',
                                'expelled' => 'EXPELLED',
                                default => strtoupper($status),
                            };
                            $counter = $status.'_count';
                            if (array_key_exists($counter, $counts)) {
                                $counts[$counter]++;
                            }
                        } elseif ($attendance === 'absent') {
                            $resultStatus = 'fail';
                            $failReasons[] = 'ABSENT_IN_VIVA';
                            $counts['academic_processed_count']++;
                            $counts['fail_count']++;
                            $counts['absent_count']++;
                        } else {
                            $counts['academic_processed_count']++;
                            if ($row->mark !== null && (float) $row->mark >= $this->rules->passMark()) {
                                $resultStatus = 'pass';
                                $counts['pass_count']++;
                            } else {
                                $resultStatus = 'fail';
                                $failReasons[] = 'BELOW_VIVA_PASS_MARK';
                                $counts['fail_count']++;
                            }
                        }

                        $snapshot = [
                            'attendance' => strtoupper($attendance),
                            'mark' => $row->mark === null ? null : (float) $row->mark,
                            'full_mark' => $this->rules->fullMark(),
                            'pass_percent' => $this->rules->passPercent(),
                            'pass_mark' => $this->rules->passMark(),
                            'result' => strtoupper($resultStatus),
                            'fail_reasons' => $failReasons,
                            'candidate_status' => strtoupper($status),
                            'written_qualified_track' => (string) $row->written_qualified_track,
                            'processing_version' => (int) $run->processing_version,
                            'processed_at' => $now->toIso8601String(),
                        ];

                        $updates[] = [
                            'id' => $row->id,
                            'viva_result_status' => $resultStatus,
                            'viva_fail_reasons' => $failReasons === [] ? null : json_encode($failReasons),
                            'processing_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'processing_version' => $run->processing_version,
                            'processing_run_id' => $run->id,
                            'processed_by' => $actorId,
                            'processed_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $this->bulkUpdateExistingResults($updates);

                    $processed += count($updates);
                    $run->update([
                        ...$counts,
                        'processed_rows' => $processed,
                        'progress_percent' => $total > 0 ? round(($processed / $total) * 100, 4) : 100,
                    ]);
                });

            $summary = [
                'total' => $total,
                'academic_processed' => $counts['academic_processed_count'],
                'pass' => $counts['pass_count'],
                'fail' => $counts['fail_count'],
                'absent' => $counts['absent_count'],
                'cancelled' => $counts['cancelled_count'],
                'withheld' => $counts['withheld_count'],
                'expelled' => $counts['expelled_count'],
                'full_mark' => $this->rules->fullMark(),
                'pass_percent' => $this->rules->passPercent(),
                'pass_mark' => $this->rules->passMark(),
                'processing_version' => (int) $run->processing_version,
            ];

            $run->update([
                ...$counts,
                'status' => 'completed',
                'processed_rows' => $processed,
                'progress_percent' => 100,
                'current_step' => 'Viva result processing completed',
                'summary' => $summary,
                'finished_at' => now(),
            ]);

            $state->update([
                'status' => 'processing_completed',
                'latest_processing_run_id' => $run->id,
                'result_processed_by' => $actorId,
                'result_processed_at' => now(),
                'result_finalized_by' => null,
                'result_finalized_at' => null,
                'summary' => $summary,
                'is_stale' => false,
                'stale_reason' => null,
            ]);

            return $run->fresh();
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'failure_message' => $exception->getMessage(),
                'current_step' => 'Processing failed',
                'finished_at' => now(),
            ]);
            $state->update([
                'status' => 'reconciliation_generated',
                'is_stale' => true,
                'stale_reason' => 'The latest Viva result processing run failed and must be run again.',
            ]);

            throw $exception;
        }
    }

    /**
     * Update only existing Viva result rows.
     *
     * Result processing must never insert a new viva_results row. Using upsert
     * here makes MySQL validate the insert branch and therefore requires every
     * non-null source/identity column. A CASE-based UPDATE avoids that unsafe
     * insert path while preserving one bulk write per processing chunk.
     *
     * @param list<array<string, mixed>> $updates
     */
    private function bulkUpdateExistingResults(array $updates): void
    {
        if ($updates === []) {
            return;
        }

        $columns = [
            'viva_result_status',
            'viva_fail_reasons',
            'processing_snapshot',
            'processing_version',
            'processing_run_id',
            'processed_by',
            'processed_at',
            'updated_at',
        ];

        $assignments = [];
        $bindings = [];

        foreach ($columns as $column) {
            $case = "`{$column}` = CASE `id`";

            foreach ($updates as $row) {
                $case .= ' WHEN ? THEN ?';
                $bindings[] = (int) $row['id'];
                $bindings[] = $row[$column] ?? null;
            }

            $case .= " ELSE `{$column}` END";
            $assignments[] = $case;
        }

        $ids = array_map(
            static fn (array $row): int => (int) $row['id'],
            $updates
        );

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        array_push($bindings, ...$ids);

        $sql = sprintf(
            'UPDATE `viva_results` SET %s WHERE `id` IN (%s)',
            implode(', ', $assignments),
            $placeholders
        );

        $affected = DB::connection('exam')->update($sql, $bindings);

        if ($affected > count($updates)) {
            throw new RuntimeException('Viva result bulk update affected more rows than expected.');
        }
    }

}
