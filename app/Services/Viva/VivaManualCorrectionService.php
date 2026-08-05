<?php

namespace App\Services\Viva;

use App\Enums\VivaCandidateStatus;
use App\Models\User;
use App\Models\VivaProcessingState;
use App\Models\VivaResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class VivaManualCorrectionService
{
    public function __construct(
        private readonly VivaRuleConfig $rules,
        private readonly VivaAuditService $audit,
    ) {}

    /**
     * @param array<string, mixed> $input
     * @return array{changed:bool, stale:bool, changed_fields:list<string>}
     */
    public function update(VivaResult $result, array $input, User $actor): array
    {
        $reason = trim((string) ($input['reason'] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A correction reason is required.',
            ]);
        }

        $normalizedMark = $this->normalizeMark((string) ($input['mark'] ?? ''));

        $next = [
            'viva_date' => (string) ($input['viva_date'] ?? ''),
            'member_id' => trim((string) ($input['member_id'] ?? '')),
            'mark' => $normalizedMark['mark'],
            'attendance_status' => $normalizedMark['attendance_status'],
            'viva_cff' => (bool) ($input['viva_cff'] ?? false),
            'viva_em' => (bool) ($input['viva_em'] ?? false),
            'viva_phc' => (bool) ($input['viva_phc'] ?? false),
            'invalid_flag' => (bool) ($input['invalid_flag'] ?? false),
            'issue_flag' => (bool) ($input['issue_flag'] ?? false),
            'status' => strtolower((string) ($input['status'] ?? 'active')),
            'comment' => $this->nullableText($input['comment'] ?? null),
        ];

        if ($next['viva_date'] === '') {
            throw ValidationException::withMessages(['viva_date' => 'Viva date is required.']);
        }

        if ($next['member_id'] === '') {
            throw ValidationException::withMessages(['member_id' => 'Member ID is required.']);
        }

        if (! in_array($next['status'], array_map(
            static fn (VivaCandidateStatus $status): string => $status->value,
            VivaCandidateStatus::cases()
        ), true)) {
            throw ValidationException::withMessages(['status' => 'Select a valid Viva candidate status.']);
        }

        return DB::connection('exam')->transaction(function () use ($result, $next, $reason, $actor): array {
            /** @var VivaResult $locked */
            $locked = VivaResult::query()->lockForUpdate()->findOrFail($result->id);

            $before = $this->snapshot($locked);
            $changedFields = [];

            foreach ($next as $field => $value) {
                if (! $this->sameValue($field, $before[$field] ?? null, $value)) {
                    $changedFields[] = $field;
                }
            }

            if ($changedFields === []) {
                return ['changed' => false, 'stale' => false, 'changed_fields' => []];
            }

            $resultAffecting = array_values(array_intersect($changedFields, [
                'viva_date',
                'member_id',
                'mark',
                'attendance_status',
                'viva_cff',
                'viva_em',
                'viva_phc',
                'invalid_flag',
                'issue_flag',
                'status',
            ]));

            $updates = $next;

            // Reconciliation-derived flags must never survive a source/effective
            // correction as if they were still current.
            if ($resultAffecting !== []) {
                $updates['quota_mismatch'] = false;
                $updates['quota_mismatch_details'] = null;
                $updates['high_mark_review'] = false;
                $updates['validation_status'] = ($next['invalid_flag'] || $next['issue_flag'])
                    ? 'warning'
                    : 'valid';
                $updates['viva_result_status'] = 'pending';
                $updates['viva_fail_reasons'] = null;
                $updates['processing_snapshot'] = null;
                $updates['processing_version'] = null;
                $updates['processing_run_id'] = null;
                $updates['processed_by'] = null;
                $updates['processed_at'] = null;
                $updates['finalized_at'] = null;
            }

            $updates['last_edited_by'] = $actor->id;
            $updates['last_edited_at'] = now();
            $updates['last_edit_reason'] = $reason;

            $locked->update($updates);
            $locked->refresh();

            $stale = $resultAffecting !== [];
            if ($stale) {
                $state = VivaProcessingState::query()->firstOrCreate(
                    ['id' => 1],
                    ['status' => 'not_started']
                );

                $state->update([
                    'status' => $state->result_finalized_at ? 'reopened' : 'board_data_imported',
                    'is_stale' => true,
                    'stale_reason' => sprintf(
                        'Viva candidate %s was manually corrected. Regenerate reconciliation before continuing.',
                        $locked->reg
                    ),
                ]);
            }

            $after = $this->snapshot($locked);

            $this->audit->record(
                action: 'VIVA_MANUAL_CORRECTION',
                actor: $actor,
                statusBefore: (string) ($before['status'] ?? ''),
                statusAfter: (string) ($after['status'] ?? ''),
                reason: $reason,
                changedFields: $changedFields,
                summary: [
                    'reg' => $locked->reg,
                    'code' => $locked->code,
                    'stale_created' => $stale,
                    'raw_source_preserved' => true,
                ],
                before: $before,
                after: $after,
                registrationId: $locked->registration_id,
                vivaResultId: $locked->id,
            );

            return [
                'changed' => true,
                'stale' => $stale,
                'changed_fields' => $changedFields,
            ];
        }, 3);
    }

    /**
     * @return array{mark:?float,attendance_status:string}
     */
    private function normalizeMark(string $value): array
    {
        $value = trim($value);

        if (strcasecmp($value, 'ABS') === 0) {
            return ['mark' => null, 'attendance_status' => 'absent'];
        }

        if ($value === '' || ! is_numeric($value)) {
            throw ValidationException::withMessages([
                'mark' => 'Viva mark must be a valid number or ABS.',
            ]);
        }

        $mark = (float) $value;
        if ($mark < 0) {
            throw ValidationException::withMessages(['mark' => 'Viva mark cannot be negative.']);
        }

        if ($mark > $this->rules->fullMark()) {
            throw ValidationException::withMessages([
                'mark' => sprintf(
                    'Viva mark cannot exceed the configured full mark of %s.',
                    $this->rules->fullMark()
                ),
            ]);
        }

        return ['mark' => $mark, 'attendance_status' => 'appeared'];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(VivaResult $result): array
    {
        $status = $result->status instanceof \BackedEnum
            ? $result->status->value
            : (string) $result->status;

        return [
            'viva_date' => $result->viva_date?->format('Y-m-d'),
            'member_id' => (string) $result->member_id,
            'mark' => $result->mark === null ? null : (float) $result->mark,
            'attendance_status' => (string) $result->attendance_status,
            'viva_cff' => (bool) $result->viva_cff,
            'viva_em' => (bool) $result->viva_em,
            'viva_phc' => (bool) $result->viva_phc,
            'invalid_flag' => (bool) $result->invalid_flag,
            'issue_flag' => (bool) $result->issue_flag,
            'status' => $status,
            'comment' => $this->nullableText($result->comment),
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }

    private function sameValue(string $field, mixed $before, mixed $after): bool
    {
        if ($field === 'mark') {
            if ($before === null || $after === null) {
                return $before === $after;
            }

            return abs((float) $before - (float) $after) < 0.00001;
        }

        if (in_array($field, ['viva_cff', 'viva_em', 'viva_phc', 'invalid_flag', 'issue_flag'], true)) {
            return (bool) $before === (bool) $after;
        }

        return $before === $after;
    }
}
