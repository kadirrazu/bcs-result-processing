<?php

namespace App\Services\Imports;

use App\Enums\PreliminaryProcessingStatus;
use App\Enums\WrittenProcessingStatus;
use App\Models\PreliminaryImportBatch;
use App\Models\PreliminaryProcessingState;
use App\Models\RegistrationImportBatch;
use App\Models\WrittenImportBatch;
use App\Models\WrittenProcessingState;
use App\Services\Written\WrittenSubjectConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Merges only corrected staging rows into an already-approved source batch.
 *
 * Existing approved rows are deliberately left untouched. This is important
 * because an operator may already have made audited manual corrections after
 * the original approval.
 */
final class CorrectedRowMergeService
{
    public function __construct(private readonly WrittenSubjectConfig $writtenSubjects) {}

    /** @param list<int> $sourceRows */
    public function merge(string $module, int $batchId, array $sourceRows, int $actorId): array
    {
        $sourceRows = array_values(array_unique(array_map('intval', $sourceRows)));
        if ($sourceRows === []) {
            return ['merged_rows' => 0, 'remaining_invalid' => 0];
        }

        return match ($module) {
            'registration' => $this->mergeRegistration($batchId, $sourceRows, $actorId),
            'preliminary' => $this->mergePreliminary($batchId, $sourceRows, $actorId),
            'written' => $this->mergeWritten($batchId, $sourceRows, $actorId),
            default => throw new RuntimeException('Unsupported corrected-row merge module.'),
        };
    }

    /** @param list<int> $sourceRows */
    private function mergeRegistration(int $batchId, array $sourceRows, int $actorId): array
    {
        $batch = RegistrationImportBatch::query()->findOrFail($batchId);
        $rows = DB::connection('exam')->table('registration_import_staging')
            ->where('batch_id', $batchId)
            ->whereIn('source_row', $sourceRows)
            ->whereIn('validation_status', ['valid', 'warning'])
            ->orderBy('source_row')
            ->get();

        $inserted = 0;
        $updated = 0;
        $timestamp = now()->format('Y-m-d H:i:s');

        if ($rows->isNotEmpty()) {
            $regs = $rows->pluck('reg')->filter()->unique()->values()->all();
            $existing = DB::connection('exam')->table('registrations')->whereIn('reg', $regs)->get()->keyBy('reg');
            $payload = [];

            foreach ($rows as $row) {
                $payload[] = [
                    'user_id' => $row->user_id,
                    'reg' => $row->reg,
                    'national_id' => $row->national_id,
                    'name' => $row->name,
                    'father_name' => $row->father_name,
                    'mother_name' => $row->mother_name,
                    'name_bn' => $row->name_bn,
                    'father_name_bn' => $row->father_name_bn,
                    'mother_name_bn' => $row->mother_name_bn,
                    'birth_date' => $row->birth_date,
                    'sex_code' => $row->sex_code,
                    'district_code' => $row->district_code,
                    'division_code' => $row->division_code,
                    'university_code' => $row->university_code,
                    'bachelor_subject_code' => $row->bachelor_subject_code,
                    'post_related_subject_code' => $row->post_related_subject_code,
                    'cadre_category' => $row->cadre_category,
                    'has_ff_quota' => $row->has_ff_quota,
                    'has_em_quota' => $row->has_em_quota,
                    'has_phc_quota' => $row->has_phc_quota,
                    'has_quota' => $row->has_quota,
                    'status' => $row->candidate_status,
                    'validation_status' => 'valid',
                    'comment' => $this->registrationCommentWithWarnings($row->comment, $row->validation_warnings),
                    'source_batch_id' => $batchId,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                isset($existing[(string) $row->reg]) ? $updated++ : $inserted++;
            }

            DB::connection('exam')->transaction(function () use ($payload, $rows, $existing, $batchId, $timestamp): void {
                DB::connection('exam')->table('registrations')->upsert(
                    $payload,
                    ['reg'],
                    [
                        'user_id', 'national_id', 'name', 'father_name', 'mother_name',
                        'name_bn', 'father_name_bn', 'mother_name_bn', 'birth_date',
                        'sex_code', 'district_code', 'division_code', 'university_code',
                        'bachelor_subject_code', 'post_related_subject_code', 'cadre_category',
                        'has_ff_quota', 'has_em_quota', 'has_phc_quota', 'has_quota',
                        'status', 'validation_status', 'comment', 'source_batch_id', 'updated_at',
                    ],
                );

                $after = DB::connection('exam')->table('registrations')
                    ->whereIn('reg', array_column($payload, 'reg'))
                    ->get(['id', 'reg'])
                    ->keyBy('reg');

                $auditRows = [];
                foreach ($rows as $row) {
                    $registration = $after->get($row->reg);
                    if ($registration === null) {
                        throw new RuntimeException("Registration {$row->reg} was not found after corrected-row merge.");
                    }
                    $before = $existing->get($row->reg);
                    $auditRows[] = [
                        'batch_id' => $batchId,
                        'source_row' => $row->source_row,
                        'registration_id' => $registration->id,
                        'reg' => $row->reg,
                        'user_id' => $row->user_id,
                        'action' => $before === null ? 'inserted' : 'updated',
                        'warnings' => $row->validation_warnings,
                        'errors' => null,
                        'before_data' => $before === null ? null : json_encode((array) $before, JSON_UNESCAPED_UNICODE),
                        'after_data' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ];
                }
                if ($auditRows !== []) {
                    DB::connection('exam')->table('registration_import_rows')->insert($auditRows);
                }
            });
        }

        $approvedCount = (int) DB::connection('exam')->table('registrations')->where('source_batch_id', $batchId)->count();
        $remainingInvalid = $this->remainingInvalid('registration_import_staging', $batchId);
        $batch->update([
            'status' => 'approved',
            'approved_rows' => $approvedCount,
            'inserted_rows' => (int) $batch->inserted_rows + $inserted,
            'updated_rows' => (int) $batch->updated_rows + $updated,
            'progress_percent' => 100,
            'failure_message' => null,
            'approved_by' => $actorId,
            'approved_at' => now(),
            'heartbeat_at' => now(),
        ]);

        Log::channel('registration')->info('Corrected invalid registration rows merged into the approved batch.', [
            'batch_id' => $batchId, 'merged_rows' => $rows->count(), 'remaining_invalid' => $remainingInvalid, 'actor_id' => $actorId,
        ]);

        return ['merged_rows' => $rows->count(), 'remaining_invalid' => $remainingInvalid];
    }

    /** @param list<int> $sourceRows */
    private function mergePreliminary(int $batchId, array $sourceRows, int $actorId): array
    {
        $batch = PreliminaryImportBatch::query()->findOrFail($batchId);
        $rows = DB::connection('exam')->table('preliminary_import_staging')
            ->where('batch_id', $batchId)
            ->whereIn('source_row', $sourceRows)
            ->whereIn('validation_status', ['valid', 'warning'])
            ->orderBy('source_row')
            ->get();

        $inserted = 0;
        $updated = 0;
        $timestamp = now()->format('Y-m-d H:i:s');

        if ($rows->isNotEmpty()) {
            $registrationIds = $rows->pluck('registration_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
            $existing = DB::connection('exam')->table('preliminary_results')->whereIn('registration_id', $registrationIds)
                ->get(['registration_id'])->keyBy('registration_id');
            $payload = [];

            foreach ($rows as $row) {
                $payload[] = [
                    'registration_id' => $row->registration_id,
                    'user_id' => $row->user_id,
                    'reg' => $row->reg,
                    'mark' => $row->mark,
                    'raw_candidate_status' => $row->raw_candidate_status,
                    'candidate_status' => $row->candidate_status ?: 'active',
                    'result_status' => null,
                    'applied_cutoff_mark' => null,
                    'validation_status' => $row->validation_status,
                    'source_batch_id' => $batchId,
                    'finalized_at' => null,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
                isset($existing[(int) $row->registration_id]) ? $updated++ : $inserted++;
            }

            DB::connection('exam')->table('preliminary_results')->upsert(
                $payload,
                ['registration_id'],
                [
                    'user_id', 'reg', 'mark', 'raw_candidate_status', 'candidate_status',
                    'result_status', 'applied_cutoff_mark', 'validation_status',
                    'source_batch_id', 'finalized_at', 'updated_at',
                ],
            );
        }

        $approvedCount = (int) DB::connection('exam')->table('preliminary_results')->where('source_batch_id', $batchId)->count();
        $remainingInvalid = $this->remainingInvalid('preliminary_import_staging', $batchId);
        $batch->update([
            'status' => 'approved',
            'approved_rows' => $approvedCount,
            'inserted_rows' => (int) $batch->inserted_rows + $inserted,
            'updated_rows' => (int) $batch->updated_rows + $updated,
            'progress_percent' => 100,
            'failure_message' => null,
            'approved_by' => $actorId,
            'approved_at' => now(),
            'finished_at' => now(),
        ]);

        PreliminaryProcessingState::query()->updateOrCreate(['id' => 1], [
            'status' => PreliminaryProcessingStatus::MarkImported->value,
            'latest_import_batch_id' => $batchId,
            'cutoff_mark' => null,
            'cutoff_set_by' => null,
            'cutoff_set_at' => null,
            'cutoff_requires_review' => false,
            'current_cutoff_decision_id' => null,
            'latest_finalization_run_id' => null,
            'latest_reconciliation_report_id' => null,
            'reconciliation_generated_by' => null,
            'reconciliation_generated_at' => null,
            'latest_distribution_report_id' => null,
            'distribution_generated_by' => null,
            'distribution_generated_at' => null,
            'result_finalized_by' => null,
            'result_finalized_at' => null,
            'summary' => null,
        ]);

        Log::channel('preliminary')->info('Corrected invalid Preliminary rows merged into the approved batch.', [
            'batch_id' => $batchId, 'merged_rows' => $rows->count(), 'remaining_invalid' => $remainingInvalid, 'actor_id' => $actorId,
        ]);

        return ['merged_rows' => $rows->count(), 'remaining_invalid' => $remainingInvalid];
    }

    /** @param list<int> $sourceRows */
    private function mergeWritten(int $batchId, array $sourceRows, int $actorId): array
    {
        $batch = WrittenImportBatch::query()->findOrFail($batchId);
        $rows = DB::connection('exam')->table('written_import_staging')
            ->where('batch_id', $batchId)
            ->whereIn('source_row', $sourceRows)
            ->whereIn('validation_status', ['valid', 'warning'])
            ->orderBy('source_row')
            ->get();

        $inserted = 0;
        $updated = 0;
        $timestamp = now()->format('Y-m-d H:i:s');

        if ($rows->isNotEmpty()) {
            $registrationIds = $rows->pluck('registration_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
            $registrations = DB::connection('exam')->table('registrations')->whereIn('id', $registrationIds)
                ->get(['id', 'cadre_category'])->keyBy('id');
            $existing = DB::connection('exam')->table('written_results')->whereIn('registration_id', $registrationIds)
                ->get(['id', 'registration_id', 'status'])->keyBy('registration_id');

            $resultPayload = [];
            foreach ($rows as $row) {
                $registration = $registrations->get((int) $row->registration_id);
                if ($registration === null) {
                    continue;
                }
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

            DB::connection('exam')->transaction(function () use ($resultPayload, $rows, $registrations, $timestamp): void {
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

                $resultIds = DB::connection('exam')->table('written_results')
                    ->whereIn('registration_id', collect($resultPayload)->pluck('registration_id')->all())
                    ->get(['id', 'registration_id'])->keyBy('registration_id');
                $writtenIds = $resultIds->pluck('id')->map(fn ($id) => (int) $id)->all();
                if ($writtenIds !== []) {
                    DB::connection('exam')->table('written_candidate_marks')->whereIn('written_result_id', $writtenIds)->delete();
                }

                $markPayload = [];
                foreach ($rows as $row) {
                    $writtenResult = $resultIds->get((int) $row->registration_id);
                    if ($writtenResult === null) {
                        continue;
                    }
                    $normalized = json_decode((string) $row->normalized_marks, true) ?: [];
                    $category = (int) ($registrations->get((int) $row->registration_id)?->cadre_category ?? 0);
                    $applicable = $this->writtenApplicableSubjects($category);
                    $warnings = json_decode((string) ($row->validation_warnings ?? ''), true) ?: [];

                    foreach (array_keys($this->writtenSubjects->subjects()) as $subjectCode) {
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
                            'crash_threshold' => in_array($subjectCode, ['008', '009'], true) ? null : $this->writtenSubjects->paperCrashThreshold($subjectCode),
                            'is_applicable' => in_array($subjectCode, $applicable, true),
                            'has_warning' => $subjectWarnings !== [],
                            'warning_codes' => $subjectWarnings === [] ? null : json_encode($subjectWarnings, JSON_UNESCAPED_UNICODE),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ];
                    }
                }
                foreach (array_chunk($markPayload, max(500, (int) config('written.mark_insert_chunk_size', 2000))) as $chunk) {
                    DB::connection('exam')->table('written_candidate_marks')->insert($chunk);
                }
            });
        }

        $approvedCount = (int) DB::connection('exam')->table('written_results')->where('source_batch_id', $batchId)->count();
        $remainingInvalid = $this->remainingInvalid('written_import_staging', $batchId);
        $batch->update([
            'status' => 'approved',
            'approved_rows' => $approvedCount,
            'inserted_rows' => (int) $batch->inserted_rows + $inserted,
            'updated_rows' => (int) $batch->updated_rows + $updated,
            'progress_percent' => 100,
            'failure_message' => null,
            'approved_by' => $actorId,
            'approved_at' => now(),
            'finished_at' => now(),
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

        Log::channel('written')->info('Corrected invalid Written rows merged into the approved batch.', [
            'batch_id' => $batchId, 'merged_rows' => $rows->count(), 'remaining_invalid' => $remainingInvalid, 'actor_id' => $actorId,
        ]);

        return ['merged_rows' => $rows->count(), 'remaining_invalid' => $remainingInvalid];
    }

    private function remainingInvalid(string $table, int $batchId): int
    {
        return (int) DB::connection('exam')->table($table)
            ->where('batch_id', $batchId)
            ->whereIn('validation_status', ['invalid', 'identity_conflict'])
            ->count();
    }

    private function registrationCommentWithWarnings(?string $comment, ?string $warningsJson): ?string
    {
        $warnings = $warningsJson === null ? [] : (json_decode($warningsJson, true) ?: []);
        if ($warnings === []) {
            return $comment;
        }
        $parts = array_filter([trim((string) $comment)]);
        foreach ($warnings as $warning) {
            $parts[] = '[IMPORT WARNING] '.$warning;
        }
        return implode(PHP_EOL, $parts);
    }

    /** @return list<string> */
    private function writtenApplicableSubjects(int $category): array
    {
        return match ($category) {
            1 => $this->writtenSubjects->trackSubjects('general'),
            2 => $this->writtenSubjects->trackSubjects('technical'),
            3 => array_values(array_unique([
                ...$this->writtenSubjects->trackSubjects('general'),
                ...$this->writtenSubjects->trackSubjects('technical'),
            ])),
            default => [],
        };
    }
}
