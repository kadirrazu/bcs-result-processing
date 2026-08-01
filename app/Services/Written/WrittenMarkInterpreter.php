<?php

namespace App\Services\Written;

/** Normalizes one written mark cell without applying pass/fail or crash rules. */
final class WrittenMarkInterpreter
{
    /** @return array{raw:?string,kind:string,actual_mark:?float,normalized:?string,error:?string} */
    public function interpret(mixed $value, float $fullMark): array
    {
        $raw = $this->raw($value);

        if ($raw === null) {
            return ['raw' => null, 'kind' => 'blank', 'actual_mark' => null, 'normalized' => null, 'error' => null];
        }

        $upper = strtoupper($raw);
        if (in_array($upper, ['ABS', 'AAA'], true)) {
            return ['raw' => $raw, 'kind' => 'absent', 'actual_mark' => null, 'normalized' => 'ABS', 'error' => null];
        }

        if (! is_numeric($raw)) {
            return [
                'raw' => $raw,
                'kind' => 'invalid',
                'actual_mark' => null,
                'normalized' => null,
                'error' => 'Mark must be numeric, ABS or AAA.',
            ];
        }

        $mark = (float) $raw;
        if ($mark < 0) {
            return ['raw' => $raw, 'kind' => 'invalid', 'actual_mark' => null, 'normalized' => null, 'error' => 'Mark cannot be negative.'];
        }
        if ($mark > $fullMark) {
            return [
                'raw' => $raw,
                'kind' => 'invalid',
                'actual_mark' => null,
                'normalized' => null,
                'error' => sprintf('Mark %.2f exceeds full mark %.2f.', $mark, $fullMark),
            ];
        }

        return [
            'raw' => $raw,
            'kind' => 'numeric',
            'actual_mark' => $mark,
            'normalized' => $this->format($mark),
            'error' => null,
        ];
    }

    private function raw(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (str_ends_with($value, '.0') && preg_match('/^\d+\.0$/', $value) === 1) {
            $value = substr($value, 0, -2);
        }
        return $value;
    }

    private function format(float $value): string
    {
        $formatted = number_format($value, 2, '.', '');
        return rtrim(rtrim($formatted, '0'), '.');
    }
}
