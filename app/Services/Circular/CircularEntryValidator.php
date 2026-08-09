<?php

namespace App\Services\Circular;

use App\Models\BachelorSubject;
use App\Models\PostRelatedSubject;

final class CircularEntryValidator
{
    public function __construct(private readonly CircularCodeResolver $codes) {}

    public function validate(array $data): array
    {
        $errors = [];
        $resolved = null;

        try {
            $resolved = $this->codes->resolve((int) $data['cadre_code'], $data['sub_cadre_code'] ?? null);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                array_push($errors, ...$messages);
            }
        }

        if (($data['sub_cadre_code'] ?? null) !== null && empty($data['sub_serial'])) {
            $errors[] = 'sub_serial is required when sub_cadre_code is present.';
        }

        if ((int) ($data['post_count'] ?? 0) <= 0) {
            $errors[] = 'post_count must be greater than zero.';
        }

        $status = strtoupper(trim((string) ($data['status'] ?? '')));
        if (! in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            $errors[] = 'status must be ACTIVE or INACTIVE.';
        }

        if ($resolved && isset($data['cadre_type']) && strtoupper((string) $data['cadre_type']) !== $resolved['cadre_type']) {
            $errors[] = "cadre_type must match Cadre Master ({$resolved['cadre_type']}).";
        }

        $bachelorCodes = $this->normalizeCodes($data['bachelor_subject_codes'] ?? []);
        $prsCodes = $this->normalizeCodes($data['prs_codes'] ?? []);

        if ($resolved && $resolved['cadre_type'] === 'GG' && ($bachelorCodes !== [] || $prsCodes !== [])) {
            $errors[] = 'General (GG) circular rows cannot contain bachelor subject or PRS restrictions.';
        }

        if ($resolved && $resolved['cadre_type'] === 'TT') {
            if ($bachelorCodes === []) {
                $errors[] = 'Technical (TT) circular rows require at least one bachelor subject code.';
            }
            if ($prsCodes === []) {
                $errors[] = 'Technical (TT) circular rows require at least one PRS code.';
            }
        }

        if ($bachelorCodes !== []) {
            $existing = BachelorSubject::query()->whereIn('subject_code', $bachelorCodes)->where('is_active', true)
                ->pluck('subject_code')->map(fn ($v) => (string) $v)->all();
            foreach (array_diff($bachelorCodes, $existing) as $code) {
                $errors[] = "Unknown or inactive bachelor subject code {$code}.";
            }
        }

        if ($prsCodes !== []) {
            $existing = PostRelatedSubject::query()->whereIn('subject_code', $prsCodes)->where('is_active', true)
                ->pluck('subject_code')->map(fn ($v) => (string) $v)->all();
            foreach (array_diff($prsCodes, $existing) as $code) {
                $errors[] = "Unknown or inactive PRS code {$code}.";
            }
        }

        $normalized = [
            'cadre_serial' => (int) ($data['cadre_serial'] ?? 0),
            'sub_serial' => filled($data['sub_serial'] ?? null) ? (int) $data['sub_serial'] : null,
            'cadre_code' => (int) ($data['cadre_code'] ?? 0),
            'sub_cadre_code' => filled($data['sub_cadre_code'] ?? null) ? (int) $data['sub_cadre_code'] : null,
            'cadre_type' => $resolved['cadre_type'] ?? strtoupper((string) ($data['cadre_type'] ?? '')),
            'post_count' => (int) ($data['post_count'] ?? 0),
            'bachelor_subject_codes' => $bachelorCodes,
            'prs_codes' => $prsCodes,
            'status' => strtolower($status ?: 'ACTIVE'),
            'note' => filled($data['note'] ?? null) ? trim((string) $data['note']) : null,
        ];

        if ($resolved) {
            $normalized += [
                'effective_code' => $resolved['effective_code'],
                'cadre_name_snapshot' => $resolved['cadre_name'],
                'cadre_name_bn_snapshot' => $resolved['cadre_name_bn'],
                'post_name_snapshot' => $resolved['post_name'],
                'post_name_bn_snapshot' => $resolved['post_name_bn'],
            ];
        }

        return ['valid' => $errors === [], 'errors' => $errors, 'data' => $normalized];
    }

    public function normalizeCodes(array|string|null $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/\s*\|\s*/', trim((string) $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $parts = array_map(fn ($code) => trim((string) $code), $parts);
        $parts = array_values(array_filter($parts, fn ($code) => $code !== ''));

        return array_values(array_unique($parts));
    }
}
