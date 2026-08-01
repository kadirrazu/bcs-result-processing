<?php

namespace App\Services\Written;

use App\Enums\WrittenProcessingStatus;
use App\Models\WrittenImportBatch;
use App\Models\WrittenProcessingState;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Merges valid/warning Written staging rows and subject facts into production. */
final class WrittenApprovalService
{
    public function __construct(private readonly WrittenSubjectConfig $subjects) {}

    public function approve(int $batchId, int $approvedBy): WrittenImportBatch
    {
        $batch = WrittenImportBatch::query()->findOrFail($batchId);
        if (! in_array($batch->status, ['validated', 'approval_queued', 'failed'], true)) {
            throw new RuntimeException('Only a validated Written batch can be approved.');
        }
        if ($batch->status === 'failed' && (int) $batch->approved_rows > 0) {
            throw new RuntimeException('A partially approved Written batch cannot be retried automatically.');
        }

        $batch->update([
            'status' => 'approving', 'processed_rows' => 0, 'approved_rows' => 0,
            'inserted_rows' => 0, 'updated_rows' => 0, 'progress_percent' => 0,
            'failure_message' => null, 'finished_at' => null,
        ]);

        try {
            $eligible = DB::connection('exam')->table('written_import_staging')->where('batch_id', $batchId)
                ->whereIn('validation_status', ['valid', 'warning'])->count();
            $processed = $inserted = $updated = 0;
            $chunkSize = max(250, (int) config('written.merge_chunk_size', 1000));
            $markInsertChunk = max(250, (int) config('written.mark_insert_chunk_size', 2000));

            DB::connection('exam')->table('written_import_staging')->where('batch_id', $batchId)
                ->whereIn('validation_status', ['valid', 'warning'])->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use ($batch, $batchId, $eligible, $markInsertChunk, &$processed, &$inserted, &$updated): void {
                    $registrationIds = $rows->pluck('registration_id')->filter()->map(fn ($v) => (int) $v)->unique()->values()->all();
                    $registrations = DB::connection('exam')->table('registrations')->whereIn('id', $registrationIds)
                        ->get(['id', 'cadre_category'])->keyBy('id');
                    $existing = DB::connection('exam')->table('written_results')->whereIn('registration_id', $registrationIds)
                        ->get(['id', 'registration_id', 'status'])->keyBy('registration_id');
                    $timestamp = now()->format('Y-m-d H:i:s');
                    $resultPayload = [];

                    foreach ($rows as $row) {
                        $registration = $registrations->get((int) $row->registration_id);
                        if ($registration === null) { continue; }
                        $resultPayload[] = [
                            'registration_id' => (int) $row->registration_id,
                            'user_id' => (string) $row->user_id,
                            'reg' => (string) $row->reg,
                            'cadre_category' => (int) $registration->cadre_category,
                            'prs_code' => $row->prs_code,
                            'data_source_note' => $row->data_source_note,
                            'status' => isset($existing[(int) $row->registration_id]) ? (string) $existing[(int) $row->registration_id]->status : 'active',
                            'validation_status' => $row->validation_status,
                            'general_result_status' => null,
                            'technical_result_status' => null,
                            'written_qualified_track' => null,
                            'general_actual_total' => null,
                            'general_counted_total' => null,
                            'technical_actual_total' => null,
                            'technical_counted_total' => null,
                            'general_fail_reasons' => null,
                            'technical_fail_reasons' => null,
                            'processing_flags' => json_encode(['validation_warnings' => json_decode((string) ($row->validation_warnings ?? ''), true) ?: []], JSON_UNESCAPED_UNICODE),
                            'source_batch_id' => $batchId,
                            'finalized_at' => null,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                        isset($existing[(int) $row->registration_id]) ? $updated++ : $inserted++;
                    }

                    DB::connection('exam')->transaction(function () use ($resultPayload, $rows, $registrations, $markInsertChunk, $timestamp): void {
                        if ($resultPayload !== []) {
                            DB::connection('exam')->table('written_results')->upsert(
                                $resultPayload,
                                ['registration_id'],
                                [
                                    'user_id', 'reg', 'cadre_category', 'prs_code', 'data_source_note', 'status',
                                    'validation_status', 'general_result_status', 'technical_result_status', 'written_qualified_track',
                                    'general_actual_total', 'general_counted_total', 'technical_actual_total', 'technical_counted_total',
                                    'general_fail_reasons', 'technical_fail_reasons', 'processing_flags', 'source_batch_id', 'finalized_at', 'updated_at',
                                ],
                            );
                        }

                        $registrationIds = collect($resultPayload)->pluck('registration_id')->all();
                        $resultIds = DB::connection('exam')->table('written_results')->whereIn('registration_id', $registrationIds)
                            ->get(['id', 'registration_id'])->keyBy('registration_id');
                        $ids = $resultIds->pluck('id')->map(fn ($v) => (int) $v)->all();
                        if ($ids !== []) {
                            DB::connection('exam')->table('written_candidate_marks')->whereIn('written_result_id', $ids)->delete();
                        }

                        $markPayload = [];
                        foreach ($rows as $row) {
                            $writtenResult = $resultIds->get((int) $row->registration_id);
                            if ($writtenResult === null) { continue; }
                            $normalized = json_decode((string) $row->normalized_marks, true) ?: [];
                            $category = (int) ($registrations->get((int) $row->registration_id)?->cadre_category ?? 0);
                            $applicable = $this->applicableSubjects($category);
                            $warnings = json_decode((string) ($row->validation_warnings ?? ''), true) ?: [];

                            foreach (array_keys($this->subjects->subjects()) as $subjectCode) {
                                $cell = $normalized[$subjectCode] ?? ['raw' => null, 'kind' => 'blank', 'actual_mark' => null];
                                $subjectWarnings = array_values(array_filter($warnings, static function (string $warning) use ($subjectCode): bool {
                                    return str_contains($warning, ':'.$subjectCode.':')
                                        || ($subjectCode === '008' && str_contains($warning, ':008_009:'))
                                        || ($subjectCode === '009' && str_contains($warning, ':008_009:'));
                                }));
                                $actual = $cell['actual_mark'] ?? null;
                                $markPayload[] = [
                                    'written_result_id' => (int) $writtenResult->id,
                                    'registration_id' => (int) $row->registration_id,
                                    'subject_code' => $subjectCode,
                                    'raw_value' => $cell['raw'] ?? null,
                                    'actual_mark' => $actual,
                                    'counted_mark' => $actual,
                                    'attendance_status' => ($cell['kind'] ?? null) === 'absent' ? 'absent' : (($cell['kind'] ?? null) === 'numeric' ? 'present' : null),
                                    'paper_crashed' => false,
                                    'crash_threshold' => in_array($subjectCode, ['008', '009'], true) ? null : $this->subjects->paperCrashThreshold($subjectCode),
                                    'is_applicable' => in_array($subjectCode, $applicable, true),
                                    'has_warning' => $subjectWarnings !== [],
                                    'warning_codes' => $subjectWarnings === [] ? null : json_encode($subjectWarnings, JSON_UNESCAPED_UNICODE),
                                    'created_at' => $timestamp,
                                    'updated_at' => $timestamp,
                                ];
                            }
                        }
                        foreach (array_chunk($markPayload, $markInsertChunk) as $markRows) {
                            DB::connection('exam')->table('written_candidate_marks')->insert($markRows);
                        }
                    });

                    $processed += $rows->count();
                    $batch->update([
                        'processed_rows' => $processed, 'approved_rows' => $processed,
                        'inserted_rows' => $inserted, 'updated_rows' => $updated,
                        'progress_percent' => $eligible > 0 ? min(100, round(($processed / $eligible) * 100, 4)) : 100,
                    ]);
                });

            $staleResultIds = DB::connection('exam')->table('written_results')->where('source_batch_id', '!=', $batchId)->pluck('id')->all();
            if ($staleResultIds !== []) {
                DB::connection('exam')->transaction(function () use ($staleResultIds, $batchId): void {
                    DB::connection('exam')->table('written_candidate_marks')->whereIn('written_result_id', $staleResultIds)->delete();
                    DB::connection('exam')->table('written_results')->where('source_batch_id', '!=', $batchId)->delete();
                });
            }

            $batch->update([
                'status' => 'approved', 'processed_rows' => $processed, 'approved_rows' => $processed,
                'inserted_rows' => $inserted, 'updated_rows' => $updated, 'progress_percent' => 100,
                'approved_at' => now(), 'approved_by' => $approvedBy, 'finished_at' => now(),
            ]);

            WrittenProcessingState::query()->updateOrCreate(['id' => 1], [
                'status' => WrittenProcessingStatus::MarksImported->value,
                'latest_import_batch_id' => $batchId,
                'reconciliation_generated_by' => null,
                'reconciliation_generated_at' => null,
                'latest_reconciliation_report_id' => null,
                'latest_processing_run_id' => null,
                'paper_crash_processed_by' => null,
                'paper_crash_processed_at' => null,
                'result_finalized_by' => null,
                'result_finalized_at' => null,
                'summary' => null,
                'is_stale' => false,
                'stale_reason' => null,
            ]);

            return $batch->refresh();
        } catch (Throwable $exception) {
            $batch->update(['status' => 'failed', 'failure_message' => mb_substr($exception->getMessage(), 0, 65000), 'finished_at' => now()]);
            throw $exception;
        }
    }

    /** @return list<string> */
    private function applicableSubjects(int $category): array
    {
        return match ($category) {
            1 => $this->subjects->trackSubjects('general'),
            2 => $this->subjects->trackSubjects('technical'),
            3 => array_values(array_unique([...$this->subjects->trackSubjects('general'), ...$this->subjects->trackSubjects('technical')])),
            default => [],
        };
    }
}
