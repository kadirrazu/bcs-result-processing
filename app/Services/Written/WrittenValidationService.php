<?php

namespace App\Services\Written;

use App\Models\PostRelatedSubject;
use App\Models\WrittenImportBatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Validates Written staging rows while preserving raw source facts. */
final class WrittenValidationService
{
    public function __construct(
        private readonly WrittenSubjectConfig $subjects,
        private readonly WrittenMarkInterpreter $marks,
    ) {}

    public function validate(int $batchId, bool $allowApprovedCorrection = false): WrittenImportBatch
    {
        $batch = WrittenImportBatch::query()->findOrFail($batchId);
        if (! in_array($batch->status, ['staged', 'validation_queued', 'validated', 'failed'], true)) {
            throw new RuntimeException('This Written batch cannot be validated from its current status.');
        }
        if ((int) $batch->approved_rows > 0 && ! $allowApprovedCorrection) {
            throw new RuntimeException('An already approved Written batch can only be revalidated through the invalid-row correction workflow.');
        }

        $batch->update([
            'status' => 'validating', 'processed_rows' => 0, 'valid_rows' => 0, 'warning_rows' => 0,
            'invalid_rows' => 0, 'identity_conflict_rows' => 0, 'progress_percent' => 0,
            'failure_message' => null, 'finished_at' => null,
        ]);

        try {
            $total = DB::connection('exam')->table('written_import_staging')->where('batch_id', $batchId)->count();
            $duplicateRegs = DB::connection('exam')->table('written_import_staging')->where('batch_id', $batchId)->whereNotNull('reg')
                ->select('reg')->groupBy('reg')->havingRaw('COUNT(*) > 1')->pluck('reg')->flip();
            $duplicateUsers = DB::connection('exam')->table('written_import_staging')->where('batch_id', $batchId)->whereNotNull('user_id')
                ->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->pluck('user_id')->flip();
            $knownPrs = PostRelatedSubject::query()->where('is_active', true)->pluck('subject_code')->map(fn ($v) => strtoupper((string) $v))->flip();

            $processed = $valid = $warnings = $invalid = $identityConflicts = 0;
            $chunkSize = max(250, (int) config('written.validation_chunk_size', 3000));
            $writeChunk = max(100, (int) config('written.validation_write_chunk_size', 1500));

            DB::connection('exam')->table('written_import_staging')->where('batch_id', $batchId)->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (
                    $batch, $duplicateRegs, $duplicateUsers, $knownPrs, $total, $writeChunk,
                    &$processed, &$valid, &$warnings, &$invalid, &$identityConflicts
                ): void {
                    $regs = $rows->pluck('reg')->filter()->unique()->values()->all();
                    $users = $rows->pluck('user_id')->filter()->unique()->values()->all();

                    $registrations = ($regs === [] && $users === []) ? collect() : DB::connection('exam')->table('registrations')
                        ->where(function ($query) use ($regs, $users): void {
                            if ($regs !== []) { $query->whereIn('reg', $regs); }
                            if ($users !== []) { $regs !== [] ? $query->orWhereIn('user_id', $users) : $query->whereIn('user_id', $users); }
                        })
                        ->get(['id', 'reg', 'user_id', 'cadre_category', 'post_related_subject_code']);
                    $byReg = $registrations->keyBy('reg');
                    $byUser = $registrations->keyBy('user_id');
                    $registrationIds = $registrations->pluck('id')->map(fn ($v) => (int) $v)->all();
                    $preliminaryPass = $registrationIds === [] ? collect() : DB::connection('exam')->table('preliminary_results')
                        ->whereIn('registration_id', $registrationIds)->where('result_status', 'pass')->pluck('registration_id')->map(fn ($v) => (int) $v)->flip();

                    $updates = [];
                    $timestamp = now()->format('Y-m-d H:i:s');
                    foreach ($rows as $row) {
                        $errors = [];
                        $rowWarnings = [];
                        $identityConflict = false;
                        $registrationId = null;
                        $category = null;
                        $expectedPrs = null;

                        if ($row->reg === null || preg_match('/^\d{8}$/', (string) $row->reg) !== 1) {
                            $errors[] = 'REG must contain exactly 8 digits.';
                        }
                        if ($row->user_id === null || preg_match('/^[A-Z0-9]{1,10}$/', (string) $row->user_id) !== 1) {
                            $errors[] = 'USER must be an alphanumeric value of up to 10 characters.';
                        }
                        if ($row->reg !== null && isset($duplicateRegs[$row->reg])) {
                            $errors[] = 'REG appears more than once in this import batch.'; $identityConflict = true;
                        }
                        if ($row->user_id !== null && isset($duplicateUsers[$row->user_id])) {
                            $errors[] = 'USER appears more than once in this import batch.'; $identityConflict = true;
                        }

                        $regMatch = $row->reg === null ? null : $byReg->get($row->reg);
                        $userMatch = $row->user_id === null ? null : $byUser->get($row->user_id);
                        if ($regMatch === null && $userMatch === null) {
                            $errors[] = 'No registration matches this REG and USER.'; $identityConflict = true;
                        } elseif ($regMatch === null || $userMatch === null || (int) $regMatch->id !== (int) $userMatch->id) {
                            $errors[] = 'REG and USER do not identify the same registered candidate.'; $identityConflict = true;
                        } elseif ((string) $regMatch->user_id !== (string) $row->user_id || (string) $userMatch->reg !== (string) $row->reg) {
                            $errors[] = 'REG and USER identity mismatch.'; $identityConflict = true;
                        } else {
                            $registrationId = (int) $regMatch->id;
                            $category = (int) $regMatch->cadre_category;
                            $expectedPrs = $this->normalizeCode($regMatch->post_related_subject_code);
                            if (! isset($preliminaryPass[$registrationId])) {
                                $errors[] = 'Candidate is not a finalized Preliminary PASS candidate and is not Written-eligible.';
                            }
                        }

                        $rawPayload = json_decode((string) $row->raw_payload, true) ?: [];
                        $normalized = [];
                        foreach (array_keys($this->subjects->subjects()) as $subjectCode) {
                            if ($subjectCode === 'PRS') { continue; }
                            $cell = $this->marks->interpret($rawPayload['s'.$subjectCode.'_mark'] ?? null, $this->subjects->fullMark($subjectCode));
                            $normalized[$subjectCode] = $cell;
                            if ($cell['error'] !== null) { $errors[] = "Subject {$subjectCode}: {$cell['error']}"; }
                        }
                        $prsCell = $this->marks->interpret($rawPayload['prs_mark'] ?? null, $this->subjects->fullMark('PRS'));
                        $normalized['PRS'] = $prsCell;
                        if ($prsCell['error'] !== null) { $errors[] = 'PRS: '.$prsCell['error']; }

                        if ($category !== null) {
                            $applicable = $this->applicableSubjects($category);
                            foreach ($applicable as $subjectCode) {
                                $cell = $normalized[$subjectCode] ?? null;
                                if ($cell !== null && $cell['kind'] === 'blank') {
                                    $errors[] = "Applicable mandatory subject {$subjectCode} is blank.";
                                }
                            }

                            foreach ($normalized as $subjectCode => $cell) {
                                if (! in_array($subjectCode, $applicable, true) && $cell['kind'] === 'numeric') {
                                    $rowWarnings[] = "OUT_OF_TRACK_MARK:{$subjectCode}: numeric mark exists outside the candidate's Written track.";
                                }
                            }

                            $technicalApplies = in_array($category, [2, 3], true);
                            $prsCode = $this->normalizeCode($row->prs_code);
                            if ($technicalApplies) {
                                if ($expectedPrs === null) { $errors[] = 'Registration has no post-related subject code for Technical-track evaluation.'; }
                                if ($prsCode === null) { $errors[] = 'PRS code is required for Technical-track evaluation.'; }
                                elseif (! isset($knownPrs[$prsCode])) { $errors[] = "PRS code {$prsCode} is not an active post-related subject."; }
                                elseif ($expectedPrs !== null && $prsCode !== $expectedPrs) {
                                    $rowWarnings[] = "PRS_MISMATCH: imported {$prsCode}, registration expects {$expectedPrs}.";
                                }
                            } elseif ($prsCode !== null) {
                                if (! isset($knownPrs[$prsCode])) { $errors[] = "PRS code {$prsCode} is not an active post-related subject."; }
                                else { $rowWarnings[] = "OUT_OF_TRACK_PRS_CODE:{$prsCode}: PRS code exists for a General-only candidate."; }
                            }

                            $this->appendHighMarkWarnings($normalized, $applicable, $rowWarnings);
                        }

                        $rowWarnings = array_values(array_unique($rowWarnings));
                        $errors = array_values(array_unique($errors));
                        if ($identityConflict) {
                            $status = 'identity_conflict'; $identityConflicts++; $invalid++;
                        } elseif ($errors !== []) {
                            $status = 'invalid'; $invalid++;
                        } elseif ($rowWarnings !== []) {
                            $status = 'warning'; $warnings++;
                        } else {
                            $status = 'valid'; $valid++;
                        }

                        $updates[] = [
                            'id' => $row->id,
                            'batch_id' => $row->batch_id,
                            'source_row' => $row->source_row,
                            // Required by written_import_staging. Laravel upsert() compiles
                            // INSERT ... ON DUPLICATE KEY UPDATE, so all non-nullable insert
                            // columns must be present even when the row already exists.
                            'raw_payload' => $row->raw_payload,
                            'registration_id' => $registrationId,
                            'user_id' => $row->user_id,
                            'reg' => $row->reg,
                            'normalized_marks' => json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'prs_code' => $this->normalizeCode($row->prs_code),
                            'prs_mark' => $prsCell['actual_mark'],
                            'data_source_note' => $row->data_source_note,
                            'status' => 'active',
                            'validation_status' => $status,
                            'validation_errors' => $errors === [] ? null : json_encode($errors, JSON_UNESCAPED_UNICODE),
                            'validation_warnings' => $rowWarnings === [] ? null : json_encode($rowWarnings, JSON_UNESCAPED_UNICODE),
                            'updated_at' => $timestamp,
                        ];
                    }

                    foreach (array_chunk($updates, $writeChunk) as $writeRows) {
                        DB::connection('exam')->table('written_import_staging')->upsert(
                            $writeRows,
                            ['id'],
                            ['registration_id', 'user_id', 'reg', 'normalized_marks', 'prs_code', 'prs_mark', 'data_source_note', 'status', 'validation_status', 'validation_errors', 'validation_warnings', 'updated_at'],
                        );
                    }

                    $processed += $rows->count();
                    $batch->update([
                        'processed_rows' => $processed, 'valid_rows' => $valid, 'warning_rows' => $warnings,
                        'invalid_rows' => $invalid, 'identity_conflict_rows' => $identityConflicts,
                        'progress_percent' => $total > 0 ? min(100, round(($processed / $total) * 100, 4)) : 100,
                    ]);
                });

            $batch->update([
                'status' => 'validated', 'processed_rows' => $processed, 'valid_rows' => $valid,
                'warning_rows' => $warnings, 'invalid_rows' => $invalid, 'identity_conflict_rows' => $identityConflicts,
                'progress_percent' => 100, 'validated_at' => now(), 'finished_at' => now(),
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

    /** @param array<string,array<string,mixed>> $normalized @param list<string> $applicable @param list<string> $warnings */
    private function appendHighMarkWarnings(array $normalized, array $applicable, array &$warnings): void
    {
        $combined = $this->subjects->combined008009();
        foreach ($applicable as $subjectCode) {
            if (in_array($subjectCode, $combined, true)) { continue; }
            $cell = $normalized[$subjectCode] ?? null;
            if (($cell['kind'] ?? null) === 'numeric' && (float) $cell['actual_mark'] >= $this->subjects->highMarkThreshold($subjectCode)) {
                $warnings[] = sprintf('HIGH_MARK_REVIEW:%s: %.2f/%.2f meets or exceeds %.2f%%.', $subjectCode, $cell['actual_mark'], $this->subjects->fullMark($subjectCode), (float) config('written.high_mark_review_percent', 75));
            }
        }

        if (count(array_intersect($combined, $applicable)) === count($combined)) {
            $a = $normalized[$combined[0]] ?? null; $b = $normalized[$combined[1]] ?? null;
            if (($a['kind'] ?? null) === 'numeric' && ($b['kind'] ?? null) === 'numeric') {
                $actual = (float) $a['actual_mark'] + (float) $b['actual_mark'];
                $full = $this->subjects->fullMark($combined[0]) + $this->subjects->fullMark($combined[1]);
                $threshold = $full * ((float) config('written.high_mark_review_percent', 75) / 100);
                if ($actual >= $threshold) {
                    $warnings[] = sprintf('HIGH_MARK_REVIEW:008_009: %.2f/%.2f combined meets or exceeds %.2f%%.', $actual, $full, (float) config('written.high_mark_review_percent', 75));
                }
            }
        }
    }

    private function normalizeCode(mixed $value): ?string
    {
        $value = strtoupper(trim((string) ($value ?? '')));
        if (str_ends_with($value, '.0') && preg_match('/^\d+\.0$/', $value) === 1) { $value = substr($value, 0, -2); }
        return $value === '' ? null : $value;
    }
}
