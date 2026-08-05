<?php

namespace App\Support;

final class VivaAuditValuePresenter
{
    public static function label(string $field): string
    {
        return [
            'viva_date' => 'Viva Date',
            'member_id' => 'Member ID',
            'mark' => 'Mark',
            'attendance_status' => 'Attendance',
            'viva_cff' => 'Viva CFF',
            'viva_em' => 'Viva EM',
            'viva_phc' => 'Viva PHC',
            'invalid_flag' => 'Invalid Flag',
            'issue_flag' => 'Issue Flag',
            'status' => 'Viva Status',
            'comment' => 'Operator Comment',
        ][$field] ?? str($field)->replace('_', ' ')->title()->toString();
    }

    public static function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (in_array($field, [
            'viva_cff',
            'viva_em',
            'viva_phc',
            'invalid_flag',
            'issue_flag',
        ], true)) {
            return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Yes' : 'No';
        }

        if ($field === 'attendance_status') {
            return strtoupper((string) $value);
        }

        if ($field === 'status') {
            return strtoupper((string) $value);
        }

        if ($field === 'mark' && is_numeric($value)) {
            $number = (float) $value;

            return fmod($number, 1.0) === 0.0
                ? number_format($number, 0)
                : rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ?: '—';
        }

        return (string) $value;
    }
}
