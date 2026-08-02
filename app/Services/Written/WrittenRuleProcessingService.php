<?php

namespace App\Services\Written;

use App\Enums\WrittenProcessingStatus;
use App\Models\WrittenProcessingRun;
use App\Models\WrittenProcessingState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Applies attendance, paper-crash, totals, track PASS/FAIL and qualified-track preview rules. */
final class WrittenRuleProcessingService
{
    public function __construct(private readonly WrittenSubjectConfig $subjects) {}

    public function process(int $runId, int $actorId): WrittenProcessingRun
    {
        $run = WrittenProcessingRun::query()->findOrFail($runId);
        $state = WrittenProcessingState::query()->firstOrCreate(['id' => 1], ['status' => WrittenProcessingStatus::NotStarted->value]);

        if ($state->reconciliation_generated_at === null || $state->is_stale) {
            throw new RuntimeException('Generate a current Written reconciliation before processing Written rules.');
        }

        $connection = DB::connection('exam');
        $total = $connection->table('written_results')->count();
        $chunkSize = max(250, (int) config('written.rule_processing_chunk_size', 1000));
        $markWriteChunk = max(500, (int) config('written.rule_mark_write_chunk_size', 2500));

        $run->update([
            'status' => 'running',
            'total_rows' => $total,
            'processed_rows' => 0,
            'progress_percent' => 0,
            'current_step' => 'Applying paper crash and track rules',
            'started_at' => now(),
            'failure_message' => null,
        ]);

        $processed = 0;

        try {
            $connection->table('written_results')
                ->select([
                    'id', 'registration_id', 'user_id', 'reg', 'cadre_category', 'status',
                    'validation_status', 'source_batch_id', 'created_at', 'processing_flags',
                ])
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use ($connection, $run, $total, $markWriteChunk, &$processed): void {
                    $ids = $rows->pluck('id')->map(fn ($id) => (int) $id)->all();
                    $allMarks = $connection->table('written_candidate_marks')
                        ->whereIn('written_result_id', $ids)
                        ->orderBy('written_result_id')
                        ->orderBy('id')
                        ->get()
                        ->groupBy('written_result_id');

                    $markPayload = [];
                    $resultPayload = [];
                    $timestamp = now()->format('Y-m-d H:i:s');
                    $isoTimestamp = now()->toIso8601String();

                    foreach ($rows as $row) {
                        $marks = $allMarks->get((int) $row->id, collect())->keyBy('subject_code');
                        [$candidateMarkPayload, $resultUpdate] = $this->processCandidateInMemory($row, $marks, $timestamp, $isoTimestamp);
                        array_push($markPayload, ...$candidateMarkPayload);
                        $resultPayload[] = $resultUpdate;
                        $processed++;
                    }

                    $connection->transaction(function () use ($connection, $markPayload, $resultPayload, $markWriteChunk): void {
                        foreach (array_chunk($markPayload, $markWriteChunk) as $markRows) {
                            $connection->table('written_candidate_marks')->upsert(
                                $markRows,
                                ['id'],
                                ['counted_mark', 'paper_crashed', 'crash_threshold', 'updated_at'],
                            );
                        }

                        if ($resultPayload !== []) {
                            $connection->table('written_results')->upsert(
                                $resultPayload,
                                ['id'],
                                [
                                    'general_result_status', 'technical_result_status', 'written_qualified_track',
                                    'general_actual_total', 'general_counted_total', 'technical_actual_total', 'technical_counted_total',
                                    'general_fail_reasons', 'technical_fail_reasons', 'processing_flags', 'finalized_at', 'updated_at',
                                ],
                            );
                        }
                    });

                    $run->update([
                        'processed_rows' => $processed,
                        'progress_percent' => $total > 0 ? min(100, round(($processed / $total) * 100, 4)) : 100,
                        'current_step' => 'Applying paper crash and track rules',
                    ]);
                }, 'id');

            $summary = $this->buildSummary();
            $run->update([
                'status' => 'completed',
                'processed_rows' => $processed,
                'progress_percent' => 100,
                'current_step' => 'Completed',
                'finished_at' => now(),
            ]);

            $state->update([
                'status' => WrittenProcessingStatus::ProcessingReady->value,
                'latest_processing_run_id' => $runId,
                'paper_crash_processed_at' => now(),
                'paper_crash_processed_by' => $actorId,
                'result_finalized_at' => null,
                'result_finalized_by' => null,
                'summary' => array_merge((array) $state->summary, ['rule_processing' => $summary]),
                'is_stale' => false,
                'stale_reason' => null,
            ]);

            return $run->refresh();
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'current_step' => 'Failed',
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }

    /**
     * Computes a candidate entirely in memory; database writes are deferred to chunk-level bulk upserts.
     *
     * @return array{0:list<array<string,mixed>>,1:array<string,mixed>}
     */
    private function processCandidateInMemory(object $row, $marks, string $timestamp, string $isoTimestamp): array
    {
        $category = (int) $row->cadre_category;
        $applicable = match ($category) {
            1 => $this->subjects->trackSubjects('general'),
            2 => $this->subjects->trackSubjects('technical'),
            3 => array_values(array_unique([...$this->subjects->trackSubjects('general'), ...$this->subjects->trackSubjects('technical')])),
            default => [],
        };

        $candidateIsActive = (string) $row->status === 'active';
        $crashes = [];

        foreach ($marks as $code => $mark) {
            $subjectCode = (string) $code;
            $actual = $mark->actual_mark === null ? null : (float) $mark->actual_mark;
            $mark->counted_mark = $candidateIsActive ? $actual : null;
            $mark->paper_crashed = 0;
            $mark->crash_threshold = null;

            if ($candidateIsActive
                && in_array($subjectCode, $applicable, true)
                && ! in_array($subjectCode, ['008', '009'], true)
                && $actual !== null) {
                $threshold = $this->subjects->paperCrashThreshold($subjectCode);
                $mark->crash_threshold = $threshold;
                if ($actual < $threshold) {
                    $mark->counted_mark = 0;
                    $mark->paper_crashed = 1;
                    $crashes[] = $subjectCode;
                }
            }
        }

        $m008 = $marks->get('008');
        $m009 = $marks->get('009');
        if ($candidateIsActive
            && $m008 && $m009
            && in_array('008', $applicable, true)
            && in_array('009', $applicable, true)
            && $m008->actual_mark !== null
            && $m009->actual_mark !== null) {
            $combinedThreshold = ($this->subjects->fullMark('008') + $this->subjects->fullMark('009'))
                * ((float) config('written.paper_crash_percent', 30) / 100);

            if (((float) $m008->actual_mark + (float) $m009->actual_mark) < $combinedThreshold) {
                foreach ([$m008, $m009] as $combinedMark) {
                    $combinedMark->counted_mark = 0;
                    $combinedMark->paper_crashed = 1;
                    $combinedMark->crash_threshold = $combinedThreshold;
                }
                $crashes[] = '008_009';
            }
        }

        $allApplicable = collect($applicable)->map(fn (string $code) => $marks->get($code))->filter();
        $completelyAbsent = $allApplicable->isNotEmpty()
            && $allApplicable->every(fn ($mark) => $mark->attendance_status === 'absent');

        $general = $this->evaluateTrack('general', $category, $marks, $completelyAbsent, (string) $row->status);
        $technical = $this->evaluateTrack('technical', $category, $marks, $completelyAbsent, (string) $row->status);
        $qualified = $this->qualifiedTrack($category, $general['status'], $technical['status']);

        $existingFlags = json_decode((string) ($row->processing_flags ?? ''), true) ?: [];
        $flags = array_merge($existingFlags, [
            'paper_crash' => array_values(array_unique($crashes)),
            'completely_absent' => $completelyAbsent,
            'processing_excluded' => ! $candidateIsActive,
            'processing_excluded_status' => $candidateIsActive ? null : (string) $row->status,
            'general' => $general['flags'],
            'technical' => $technical['flags'],
            'rules_processed_at' => $isoTimestamp,
        ]);

        $markPayload = [];
        foreach ($marks as $mark) {
            // Include every required/non-nullable column because MySQL upsert is INSERT ... ON DUPLICATE KEY UPDATE.
            $markPayload[] = [
                'id' => (int) $mark->id,
                'written_result_id' => (int) $mark->written_result_id,
                'registration_id' => (int) $mark->registration_id,
                'subject_code' => (string) $mark->subject_code,
                'raw_value' => $mark->raw_value,
                'actual_mark' => $mark->actual_mark,
                'counted_mark' => $mark->counted_mark,
                'attendance_status' => $mark->attendance_status,
                'paper_crashed' => (bool) $mark->paper_crashed,
                'crash_threshold' => $mark->crash_threshold,
                'is_applicable' => (bool) $mark->is_applicable,
                'has_warning' => (bool) $mark->has_warning,
                'warning_codes' => $mark->warning_codes,
                'created_at' => $mark->created_at,
                'updated_at' => $timestamp,
            ];
        }

        $resultPayload = [
            'id' => (int) $row->id,
            'registration_id' => (int) $row->registration_id,
            'user_id' => (string) $row->user_id,
            'reg' => (string) $row->reg,
            'cadre_category' => (int) $row->cadre_category,
            'status' => (string) $row->status,
            'validation_status' => (string) $row->validation_status,
            'source_batch_id' => (int) $row->source_batch_id,
            'general_result_status' => $general['status'],
            'technical_result_status' => $technical['status'],
            'written_qualified_track' => $qualified,
            'general_actual_total' => $general['actual_total'],
            'general_counted_total' => $general['counted_total'],
            'technical_actual_total' => $technical['actual_total'],
            'technical_counted_total' => $technical['counted_total'],
            'general_fail_reasons' => $general['reasons'] === [] ? null : json_encode($general['reasons'], JSON_UNESCAPED_UNICODE),
            'technical_fail_reasons' => $technical['reasons'] === [] ? null : json_encode($technical['reasons'], JSON_UNESCAPED_UNICODE),
            'processing_flags' => json_encode($flags, JSON_UNESCAPED_UNICODE),
            'finalized_at' => null,
            'created_at' => $row->created_at,
            'updated_at' => $timestamp,
        ];

        return [$markPayload, $resultPayload];
    }

    /** @return array{status:?string,actual_total:?float,counted_total:?float,reasons:array,flags:array} */
    private function evaluateTrack(string $track, int $category, $marks, bool $completelyAbsent, string $candidateStatus): array
    {
        $applicableTrack = ($track === 'general' && in_array($category, [1, 3], true))
            || ($track === 'technical' && in_array($category, [2, 3], true));

        if (! $applicableTrack) {
            return ['status' => 'not_applicable', 'actual_total' => null, 'counted_total' => null, 'reasons' => [], 'flags' => ['applicable' => false]];
        }

        if ($candidateStatus !== 'active') {
            $code = match ($candidateStatus) {
                'cancelled' => 'CANCELLED',
                'withheld' => 'WITHHELD',
                'expelled' => 'EXPELLED',
                default => 'EXCLUDED_BY_STATUS',
            };
            return [
                'status' => 'not_applicable',
                'actual_total' => null,
                'counted_total' => null,
                'reasons' => [['code' => $code, 'track' => strtoupper($track)]],
                'flags' => ['applicable' => true, 'excluded_by_status' => $candidateStatus],
            ];
        }

        $subjectCodes = $this->subjects->trackSubjects($track);
        $trackMarks = collect($subjectCodes)->mapWithKeys(fn (string $code) => [$code => $marks->get($code)]);
        $absentCodes = $trackMarks->filter(fn ($mark) => $mark && $mark->attendance_status === 'absent')->keys()->values()->all();
        $actualTotal = (float) $trackMarks->sum(fn ($mark) => $mark?->actual_mark === null ? 0 : (float) $mark->actual_mark);
        $countedTotal = (float) $trackMarks->sum(fn ($mark) => $mark?->counted_mark === null ? 0 : (float) $mark->counted_mark);
        $required = $this->subjects->trackPassThreshold($track);
        $reasons = [];

        if ($completelyAbsent) {
            $reasons[] = ['code' => 'COMPLETELY_ABSENT', 'track' => strtoupper($track)];
        } elseif ($absentCodes !== []) {
            $reasons[] = ['code' => 'MANDATORY_ABSENT', 'track' => strtoupper($track), 'subjects' => $absentCodes];
        }

        if ($countedTotal < $required) {
            $reasons[] = [
                'code' => 'TOTAL_BELOW_PASS_THRESHOLD',
                'track' => strtoupper($track),
                'counted_total' => $countedTotal,
                'required_total' => $required,
            ];
        }

        $pass = $absentCodes === [] && ! $completelyAbsent && $countedTotal >= $required;

        return [
            'status' => $pass ? 'pass' : 'fail',
            'actual_total' => $actualTotal,
            'counted_total' => $countedTotal,
            'reasons' => $reasons,
            'flags' => ['applicable' => true, 'absent_subjects' => $absentCodes, 'required_total' => $required],
        ];
    }

    private function qualifiedTrack(int $category, ?string $general, ?string $technical): ?string
    {
        return match ($category) {
            1 => $general === 'pass' ? 'GG' : null,
            2 => $technical === 'pass' ? 'TT' : null,
            3 => match (true) {
                $general === 'pass' && $technical === 'pass' => 'GT',
                $general === 'pass' => 'GN',
                $technical === 'pass' => 'T',
                default => null,
            },
            default => null,
        };
    }

    private function buildSummary(): array
    {
        $base = DB::connection('exam')->table('written_results');
        $qualified = [];
        foreach (['GG', 'TT', 'GT', 'GN', 'T'] as $track) {
            $qualified[$track] = (clone $base)->where('written_qualified_track', $track)->count();
        }

        return [
            'processed' => (clone $base)->count(),
            'general_pass' => (clone $base)->where('general_result_status', 'pass')->count(),
            'general_fail' => (clone $base)->where('general_result_status', 'fail')->count(),
            'technical_pass' => (clone $base)->where('technical_result_status', 'pass')->count(),
            'technical_fail' => (clone $base)->where('technical_result_status', 'fail')->count(),
            'paper_crash_candidates' => DB::connection('exam')->table('written_candidate_marks')->where('paper_crashed', 1)->distinct()->count('written_result_id'),
            'qualified_tracks' => $qualified,
            'effective_categories' => [
                'GG' => $qualified['GG'] + $qualified['GN'],
                'TT' => $qualified['TT'] + $qualified['T'],
                'GT' => $qualified['GT'],
            ],
        ];
    }
}
