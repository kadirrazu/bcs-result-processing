<?php

namespace App\Services\Written;

use App\Enums\WrittenProcessingStatus;
use App\Models\PostRelatedSubject;
use App\Models\User;
use App\Models\WrittenCandidateMark;
use App\Models\WrittenProcessingState;
use App\Models\WrittenResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Applies audited manual corrections to approved Written facts.
 *
 * Source note is intentionally immutable here. Result-affecting changes do not
 * silently recalculate downstream facts; they invalidate reconciliation/rule
 * processing so the operator must regenerate them through the normal pipeline.
 */
final class WrittenResultEditService
{
    public function __construct(
        private readonly WrittenMarkInterpreter $markInterpreter,
        private readonly WrittenSubjectConfig $subjects,
        private readonly WrittenAuditService $audit,
    ) {}

    /**
     * @param array<string,mixed> $markInputs
     * @return array{result:WrittenResult,changed:bool,stale:bool,changed_fields:array<string,array{before:mixed,after:mixed}>}
     */
    public function update(
        WrittenResult $result,
        array $markInputs,
        ?string $prsCode,
        string $status,
        ?string $comment,
        string $reason,
        User $actor,
    ): array {
        $result->load('marks');
        $before = $this->snapshot($result);
        $category = (int) $result->cadre_category;
        $applicable = $this->applicableSubjects($category);
        $normalizedPrsCode = $this->normalizeCode($prsCode);
        $normalizedComment = $this->nullableText($comment);

        $registration = DB::connection('exam')->table('registrations')
            ->where('id', $result->registration_id)
            ->first(['post_related_subject_code']);
        $expectedPrsCode = $this->normalizeCode($registration?->post_related_subject_code);

        $interpreted = [];
        $errors = [];
        foreach (array_keys($this->subjects->subjects()) as $subjectCode) {
            $cell = $this->markInterpreter->interpret($markInputs[$subjectCode] ?? null, $this->subjects->fullMark($subjectCode));
            $interpreted[$subjectCode] = $cell;

            if ($cell['error'] !== null) {
                $errors["marks.{$subjectCode}"][] = $cell['error'];
            }
            if (in_array($subjectCode, $applicable, true) && $cell['kind'] === 'blank') {
                $errors["marks.{$subjectCode}"][] = "Applicable mandatory subject {$subjectCode} cannot be blank.";
            }
        }

        $technicalApplies = in_array($category, [2, 3], true);
        if ($technicalApplies && $normalizedPrsCode === null) {
            $errors['prs_code'][] = 'PRS code is required for Technical-track evaluation.';
        }
        if ($normalizedPrsCode !== null && ! $this->knownPrsCode($normalizedPrsCode)) {
            $errors['prs_code'][] = "PRS code {$normalizedPrsCode} is not an active post-related subject.";
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $warningsBySubject = array_fill_keys(array_keys($this->subjects->subjects()), []);
        $candidateWarnings = [];
        foreach ($interpreted as $subjectCode => $cell) {
            if (! in_array($subjectCode, $applicable, true) && $cell['kind'] === 'numeric') {
                $message = "OUT_OF_TRACK_MARK:{$subjectCode}: numeric mark exists outside the candidate's Written track.";
                $warningsBySubject[$subjectCode][] = $message;
                $candidateWarnings[] = $message;
            }
        }

        $this->appendHighMarkWarnings($interpreted, $applicable, $warningsBySubject, $candidateWarnings);

        if ($technicalApplies && $normalizedPrsCode !== null && $expectedPrsCode !== null && $normalizedPrsCode !== $expectedPrsCode) {
            $message = "PRS_MISMATCH: imported/manual {$normalizedPrsCode}, registration expects {$expectedPrsCode}.";
            $warningsBySubject['PRS'][] = $message;
            $candidateWarnings[] = $message;
        } elseif (! $technicalApplies && $normalizedPrsCode !== null) {
            $message = "OUT_OF_TRACK_PRS_CODE:{$normalizedPrsCode}: PRS code exists for a General-only candidate.";
            $warningsBySubject['PRS'][] = $message;
            $candidateWarnings[] = $message;
        }

        $candidateWarnings = array_values(array_unique($candidateWarnings));
        foreach ($warningsBySubject as $subjectCode => $warnings) {
            $warningsBySubject[$subjectCode] = array_values(array_unique($warnings));
        }

        $afterFacts = [
            'prs_code' => $normalizedPrsCode,
            'status' => $status,
            'comment' => $normalizedComment,
            'marks' => [],
        ];
        foreach ($interpreted as $subjectCode => $cell) {
            $afterFacts['marks'][$subjectCode] = [
                'raw_value' => $cell['raw'],
                'actual_mark' => $cell['actual_mark'],
                'attendance_status' => $cell['kind'] === 'absent' ? 'absent' : ($cell['kind'] === 'numeric' ? 'present' : null),
            ];
        }

        $changedFields = $this->changedFields($before, $afterFacts);
        if ($changedFields === []) {
            return ['result' => $result, 'changed' => false, 'stale' => false, 'changed_fields' => []];
        }

        $resultAffectingChange = collect(array_keys($changedFields))->contains(
            static fn (string $field): bool => $field === 'prs_code' || $field === 'status' || str_starts_with($field, 'marks.'),
        );

        $state = WrittenProcessingState::query()->firstOrCreate(
            ['id' => 1],
            ['status' => WrittenProcessingStatus::NotStarted->value],
        );
        $stateBefore = $state->status instanceof \BackedEnum ? $state->status->value : (string) $state->status;
        $timestamp = now();

        DB::connection('exam')->transaction(function () use (
            $result,
            $interpreted,
            $warningsBySubject,
            $candidateWarnings,
            $applicable,
            $normalizedPrsCode,
            $normalizedComment,
            $status,
            $reason,
            $actor,
            $state,
            $resultAffectingChange,
            $timestamp,
        ): void {
            foreach ($interpreted as $subjectCode => $cell) {
                $warnings = $warningsBySubject[$subjectCode];
                WrittenCandidateMark::query()->updateOrCreate(
                    ['written_result_id' => $result->id, 'subject_code' => $subjectCode],
                    [
                        'registration_id' => $result->registration_id,
                        'raw_value' => $cell['raw'],
                        'actual_mark' => $cell['actual_mark'],
                        // Until normal rule processing is rerun, counted value mirrors source truth.
                        'counted_mark' => $cell['actual_mark'],
                        'attendance_status' => $cell['kind'] === 'absent' ? 'absent' : ($cell['kind'] === 'numeric' ? 'present' : null),
                        'paper_crashed' => false,
                        'crash_threshold' => in_array($subjectCode, ['008', '009'], true) ? null : $this->subjects->paperCrashThreshold($subjectCode),
                        'is_applicable' => in_array($subjectCode, $applicable, true),
                        'has_warning' => $warnings !== [],
                        'warning_codes' => $warnings === [] ? null : $warnings,
                    ],
                );
            }

            $existingFlags = is_array($result->processing_flags) ? $result->processing_flags : [];
            $existingFlags['validation_warnings'] = $candidateWarnings;
            if ($resultAffectingChange) {
                unset($existingFlags['paper_crash'], $existingFlags['completely_absent'], $existingFlags['general'], $existingFlags['technical'], $existingFlags['rules_processed_at']);
            }

            $result->update([
                'prs_code' => $normalizedPrsCode,
                'status' => $status,
                'validation_status' => $candidateWarnings === [] ? 'valid' : 'warning',
                'processing_flags' => $existingFlags,
                'comment' => $normalizedComment,
                'last_edited_by' => $actor->id,
                'last_edited_at' => $timestamp,
                'last_edit_reason' => $reason,
                ...($resultAffectingChange ? [
                    'general_result_status' => null,
                    'technical_result_status' => null,
                    'written_qualified_track' => null,
                    'general_actual_total' => null,
                    'general_counted_total' => null,
                    'technical_actual_total' => null,
                    'technical_counted_total' => null,
                    'general_fail_reasons' => null,
                    'technical_fail_reasons' => null,
                    'finalized_at' => null,
                ] : []),
            ]);

            if ($resultAffectingChange) {
                // A candidate correction changes global reconciliation/result facts. Reset all derived
                // counted/crash/result fields so no stale PASS/FAIL is exposed as current truth.
                DB::connection('exam')->table('written_candidate_marks')->update([
                    'counted_mark' => DB::raw('actual_mark'),
                    'paper_crashed' => false,
                    'updated_at' => $timestamp,
                ]);
                DB::connection('exam')->table('written_results')->update([
                    'general_result_status' => null,
                    'technical_result_status' => null,
                    'written_qualified_track' => null,
                    'general_actual_total' => null,
                    'general_counted_total' => null,
                    'technical_actual_total' => null,
                    'technical_counted_total' => null,
                    'general_fail_reasons' => null,
                    'technical_fail_reasons' => null,
                    'finalized_at' => null,
                    'updated_at' => $timestamp,
                ]);

                $state->update([
                    'status' => WrittenProcessingStatus::Reopened->value,
                    'latest_reconciliation_report_id' => null,
                    'reconciliation_generated_by' => null,
                    'reconciliation_generated_at' => null,
                    'latest_processing_run_id' => null,
                    'paper_crash_processed_by' => null,
                    'paper_crash_processed_at' => null,
                    'result_finalized_by' => null,
                    'result_finalized_at' => null,
                    'summary' => null,
                    'is_stale' => true,
                    'stale_reason' => "Manual Written correction for REG {$result->reg}. Regenerate reconciliation and reprocess Written rules.",
                ]);
            }
        });

        $result->refresh()->load('marks');
        $after = $this->snapshot($result);

        $this->audit->record(
            'WRITTEN_RESULT_MANUAL_EDITED',
            $actor,
            $stateBefore,
            $resultAffectingChange ? WrittenProcessingStatus::Reopened->value : $stateBefore,
            $reason,
            changedFields: $changedFields,
            summary: [
                'reg' => $result->reg,
                'user_id' => $result->user_id,
                'result_affecting_change' => $resultAffectingChange,
                'downstream_snapshots_invalidated' => $resultAffectingChange,
                'validation_warnings' => $candidateWarnings,
            ],
            before: $before,
            after: $after,
            batchId: $result->source_batch_id,
            registrationId: $result->registration_id,
            writtenResultId: $result->id,
        );

        return [
            'result' => $result,
            'changed' => true,
            'stale' => $resultAffectingChange,
            'changed_fields' => $changedFields,
        ];
    }

    /** @return array<string,mixed> */
    private function snapshot(WrittenResult $result): array
    {
        $result->loadMissing('marks');

        $marks = [];
        foreach ($result->marks->sortBy('id') as $mark) {
            $marks[(string) $mark->subject_code] = [
                'raw_value' => $mark->raw_value,
                'actual_mark' => $mark->actual_mark,
                'counted_mark' => $mark->counted_mark,
                'attendance_status' => $mark->attendance_status,
                'paper_crashed' => (bool) $mark->paper_crashed,
                'is_applicable' => (bool) $mark->is_applicable,
                'warning_codes' => $mark->warning_codes,
            ];
        }

        return [
            'id' => $result->id,
            'registration_id' => $result->registration_id,
            'reg' => $result->reg,
            'user_id' => $result->user_id,
            'cadre_category' => (int) $result->cadre_category,
            'prs_code' => $result->prs_code,
            'data_source_note' => $result->data_source_note,
            'status' => $this->enumValue($result->status),
            'validation_status' => $this->enumValue($result->validation_status),
            'general_result_status' => $this->enumValue($result->general_result_status),
            'technical_result_status' => $this->enumValue($result->technical_result_status),
            'written_qualified_track' => $this->enumValue($result->written_qualified_track),
            'general_actual_total' => $result->general_actual_total,
            'general_counted_total' => $result->general_counted_total,
            'technical_actual_total' => $result->technical_actual_total,
            'technical_counted_total' => $result->technical_counted_total,
            'general_fail_reasons' => $result->general_fail_reasons,
            'technical_fail_reasons' => $result->technical_fail_reasons,
            'comment' => $result->comment,
            'marks' => $marks,
            'last_edited_by' => $result->last_edited_by,
            'last_edited_at' => $result->last_edited_at?->toDateTimeString(),
            'last_edit_reason' => $result->last_edit_reason,
        ];
    }

    /** @return array<string,array{before:mixed,after:mixed}> */
    private function changedFields(array $before, array $afterFacts): array
    {
        $changes = [];
        foreach (['prs_code', 'status', 'comment'] as $field) {
            $old = $field === 'status' ? $before['status'] : $before[$field];
            $new = $afterFacts[$field];
            if ($this->comparable($old) !== $this->comparable($new)) {
                $changes[$field] = ['before' => $old, 'after' => $new];
            }
        }

        foreach ($afterFacts['marks'] as $subjectCode => $newMark) {
            $oldMark = $before['marks'][$subjectCode] ?? ['raw_value' => null, 'actual_mark' => null, 'attendance_status' => null];
            foreach (['raw_value', 'actual_mark', 'attendance_status'] as $part) {
                if ($this->comparable($oldMark[$part] ?? null) !== $this->comparable($newMark[$part] ?? null)) {
                    $changes["marks.{$subjectCode}.{$part}"] = [
                        'before' => $oldMark[$part] ?? null,
                        'after' => $newMark[$part] ?? null,
                    ];
                }
            }
        }

        return $changes;
    }

    /** @param array<string,array<string,mixed>> $interpreted @param list<string> $applicable */
    private function appendHighMarkWarnings(array $interpreted, array $applicable, array &$warningsBySubject, array &$candidateWarnings): void
    {
        $combined = $this->subjects->combined008009();
        foreach ($applicable as $subjectCode) {
            if (in_array($subjectCode, $combined, true)) {
                continue;
            }
            $cell = $interpreted[$subjectCode] ?? null;
            if (($cell['kind'] ?? null) === 'numeric' && (float) $cell['actual_mark'] >= $this->subjects->highMarkThreshold($subjectCode)) {
                $message = sprintf(
                    'HIGH_MARK_REVIEW:%s: %.2f/%.2f meets or exceeds %.2f%%.',
                    $subjectCode,
                    $cell['actual_mark'],
                    $this->subjects->fullMark($subjectCode),
                    (float) config('written.high_mark_review_percent', 75),
                );
                $warningsBySubject[$subjectCode][] = $message;
                $candidateWarnings[] = $message;
            }
        }

        if (count(array_intersect($combined, $applicable)) === count($combined)) {
            $first = $interpreted[$combined[0]] ?? null;
            $second = $interpreted[$combined[1]] ?? null;
            if (($first['kind'] ?? null) === 'numeric' && ($second['kind'] ?? null) === 'numeric') {
                $actual = (float) $first['actual_mark'] + (float) $second['actual_mark'];
                $full = $this->subjects->fullMark($combined[0]) + $this->subjects->fullMark($combined[1]);
                $threshold = $full * ((float) config('written.high_mark_review_percent', 75) / 100);
                if ($actual >= $threshold) {
                    $message = sprintf(
                        'HIGH_MARK_REVIEW:008_009: %.2f/%.2f combined meets or exceeds %.2f%%.',
                        $actual,
                        $full,
                        (float) config('written.high_mark_review_percent', 75),
                    );
                    foreach ($combined as $subjectCode) {
                        $warningsBySubject[$subjectCode][] = $message;
                    }
                    $candidateWarnings[] = $message;
                }
            }
        }
    }

    /** @return list<string> */
    private function applicableSubjects(int $category): array
    {
        return match ($category) {
            1 => $this->subjects->trackSubjects('general'),
            2 => $this->subjects->trackSubjects('technical'),
            3 => array_values(array_unique([
                ...$this->subjects->trackSubjects('general'),
                ...$this->subjects->trackSubjects('technical'),
            ])),
            default => [],
        };
    }

    private function knownPrsCode(string $code): bool
    {
        return PostRelatedSubject::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(subject_code) = ?', [$code])
            ->exists();
    }

    private function normalizeCode(mixed $value): ?string
    {
        $value = strtoupper(trim((string) ($value ?? '')));
        if (str_ends_with($value, '.0') && preg_match('/^\d+\.0$/', $value) === 1) {
            $value = substr($value, 0, -2);
        }

        return $value === '' ? null : $value;
    }

    private function nullableText(?string $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    private function comparable(mixed $value): string
    {
        if ($value === null) {
            return '__NULL__';
        }
        if (is_numeric($value)) {
            return number_format((float) $value, 4, '.', '');
        }

        return trim((string) $value);
    }
}
