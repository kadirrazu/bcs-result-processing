<?php

namespace App\Services\NonCadre\Circular;

use App\Models\BachelorSubject;

final class NonCadreCircularValidator
{
    /** @return array{valid:bool,data:array<string,mixed>,errors:array<int,string>} */
    public function validate(array $row): array
    {
        $errors = [];
        $text = static fn ($value): ?string => trim((string) ($value ?? '')) === '' ? null : trim((string) $value);
        $integer = static function ($value, string $field, bool $required = false) use (&$errors): ?int {
            if ($value === null || trim((string) $value) === '') {
                if ($required) $errors[] = "$field is required.";
                return null;
            }
            if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 0) {
                $errors[] = "$field must be a non-negative integer.";
                return null;
            }
            return (int) $value;
        };

        $postGrade = $integer($row['post_grade'] ?? null, 'post_grade');
        $postSerial = $integer($row['post_serial'] ?? null, 'post_serial', true);
        $postSubSerial = $integer($row['post_sub_serial'] ?? null, 'post_sub_serial');
        $postCount = $integer($row['post_count'] ?? null, 'post_count', true);
        if ($postCount !== null && $postCount < 1) $errors[] = 'post_count must be at least 1.';

        $requiredText = ['ministry','ministry_bn','entity','entity_bn','post_title','post_title_bn','post_code','status'];
        $normalizedText = [];
        foreach ($requiredText as $field) {
            $normalizedText[$field] = $text($row[$field] ?? null);
            if ($normalizedText[$field] === null) $errors[] = "$field is required.";
        }

        $status = strtoupper((string) ($normalizedText['status'] ?? ''));
        if ($status !== 'ACTIVE') $errors[] = 'status must be ACTIVE for an effective Non-Cadre circular post.';

        $specialRaw = trim((string) ($row['special_requirement'] ?? '0'));
        if (! in_array($specialRaw, ['0','1'], true)) $errors[] = 'special_requirement must be 0 or 1.';
        $special = $specialRaw === '1';
        $specialNote = $text($row['special_requirement_note'] ?? null);
        if ($special && $specialNote === null) $errors[] = 'special_requirement_note is required when special_requirement is 1.';

        $subjectRaw = $text($row['bachelor_subject_codes'] ?? null);
        $subjectCodes = [];
        if ($subjectRaw !== null) {
            $subjectCodes = array_values(array_unique(array_filter(array_map('trim', explode('|', $subjectRaw)), fn ($v) => $v !== '')));
            if ($subjectCodes === []) {
                $errors[] = 'bachelor_subject_codes must be blank for all subjects or contain pipe-separated subject codes.';
            } else {
                $known = BachelorSubject::query()->whereIn('subject_code', $subjectCodes)->pluck('subject_code')->map(fn ($v) => (string) $v)->all();
                $unknown = array_values(array_diff($subjectCodes, $known));
                if ($unknown !== []) $errors[] = 'Unknown bachelor subject code(s): '.implode(', ', $unknown).'.';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'data' => [
                'post_grade' => $postGrade,
                'post_serial' => $postSerial,
                'post_sub_serial' => $postSubSerial,
                'ministry' => $normalizedText['ministry'] ?? null,
                'ministry_bn' => $normalizedText['ministry_bn'] ?? null,
                'entity' => $normalizedText['entity'] ?? null,
                'entity_bn' => $normalizedText['entity_bn'] ?? null,
                'post_title' => $normalizedText['post_title'] ?? null,
                'post_title_bn' => $normalizedText['post_title_bn'] ?? null,
                'post_code' => $normalizedText['post_code'] ?? null,
                'post_count' => $postCount,
                'bachelor_subject_codes' => $subjectCodes === [] ? null : implode('|', $subjectCodes),
                'status' => $status,
                'special_requirement' => $special,
                'special_requirement_note' => $specialNote,
            ],
        ];
    }
}
