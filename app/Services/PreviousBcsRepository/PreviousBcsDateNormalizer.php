<?php

namespace App\Services\PreviousBcsRepository;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

final class PreviousBcsDateNormalizer
{
    /** @return array{raw:?string,date:?string,error:?string} */
    public function bDate(mixed $value): array
    {
        $raw = $this->raw($value, 'b_date');
        if ($raw === null) {
            return ['raw' => null, 'date' => null, 'error' => 'b_date is required.'];
        }

        $digits = preg_replace('/\D+/', '', preg_replace('/\.0+$/', '', $raw) ?? '') ?? '';

        // Excel numeric cells can lose the leading zero from DDMMYY / DDMMYYYY.
        if (strlen($digits) === 5 || strlen($digits) === 7) {
            $digits = '0'.$digits;
        }

        if (strlen($digits) === 8) {
            $date = $this->strict('dmY', $digits);
            return [
                'raw' => $raw,
                'date' => $date,
                'error' => $date ? null : 'b_date must be a valid DDMMYYYY calendar date.',
            ];
        }

        if (strlen($digits) === 6) {
            $day = substr($digits, 0, 2);
            $month = substr($digits, 2, 2);
            $yy = (int) substr($digits, 4, 2);
            $currentTwoDigitYear = (int) date('y');
            $year = $yy <= $currentTwoDigitYear ? 2000 + $yy : 1900 + $yy;
            $date = $this->strict('dmY', $day.$month.$year);

            return [
                'raw' => $raw,
                'date' => $date,
                'error' => $date ? null : 'b_date must be a valid DDMMYY calendar date.',
            ];
        }

        return [
            'raw' => $raw,
            'date' => null,
            'error' => 'b_date must use DDMMYY or DDMMYYYY format.',
        ];
    }

    /** @return array{raw:?string,date:?string,error:?string} */
    public function optionalDob(mixed $value): array
    {
        $raw = $this->raw($value, 'dob');
        if ($raw === null) {
            return ['raw' => null, 'date' => null, 'error' => null];
        }

        foreach (['m/d/Y', 'n/j/Y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'm-d-Y', 'dmY'] as $format) {
            $date = $this->strict($format, $raw);
            if ($date !== null) {
                return ['raw' => $raw, 'date' => $date, 'error' => null];
            }
        }

        // Conservative fallback for other safely parseable date strings.
        try {
            $date = new DateTimeImmutable($raw);
            return ['raw' => $raw, 'date' => $date->format('Y-m-d'), 'error' => null];
        } catch (Throwable) {
            return ['raw' => $raw, 'date' => null, 'error' => 'dob is not a recognizable date.'];
        }
    }

    private function raw(mixed $value, string $field): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $field === 'b_date' ? $value->format('dmY') : $value->format('m/d/Y');
        }

        $raw = trim((string) ($value ?? ''));
        return $raw === '' ? null : $raw;
    }

    private function strict(string $format, string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $date->format('Y-m-d');
    }
}
