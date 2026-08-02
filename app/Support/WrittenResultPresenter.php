<?php

namespace App\Support;

final class WrittenResultPresenter
{
    public static function effectiveCategory(?string $track): string
    {
        return match ($track) {
            'GG', 'GN' => 'GG',
            'TT', 'T' => 'TT',
            'GT' => 'GT',
            default => '—',
        };
    }

    /** @param mixed $reasons */
    public static function reasons(mixed $reasons): array
    {
        if (! is_array($reasons)) {
            return [];
        }

        return array_map(static function (array $reason): string {
            $code = (string) ($reason['code'] ?? '');
            $subjects = implode(', ', array_map('strval', (array) ($reason['subjects'] ?? [])));

            return match ($code) {
                'COMPLETELY_ABSENT' => 'Absent from every applicable Written paper.',
                'MANDATORY_ABSENT' => $subjects !== ''
                    ? "Absent from mandatory subject(s): {$subjects}."
                    : 'Absent from one or more mandatory Written subjects.',
                'TOTAL_BELOW_PASS_THRESHOLD' => sprintf(
                    'Counted total %s was below the required %s.',
                    self::number($reason['counted_total'] ?? null),
                    self::number($reason['required_total'] ?? null),
                ),
                'CANCELLED' => 'Candidate was cancelled for Written processing.',
                'WITHHELD' => 'Written result is withheld.',
                'EXPELLED' => 'Candidate was expelled from Written processing.',
                default => $code !== '' ? ucwords(strtolower(str_replace('_', ' ', $code))).'.' : 'Written requirement was not met.',
            };
        }, $reasons);
    }

    private static function number(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 2, '.', '') : '—';
    }
}
