<?php

namespace App\Services\Preliminary;

/**
 * Applies the locked P2 row policy without touching the database.
 */
final class PreliminaryRowInterpreter
{
    /** @return array{mark:?string,candidate_status:string,warnings:list<string>,errors:list<string>} */
    public function interpret(mixed $rawMark, mixed $rawCandidateStatus): array
    {
        $markText = $this->plain($rawMark);
        $statusText = trim((string) ($rawCandidateStatus ?? ''));
        $warnings = [];
        $errors = [];
        $mark = null;

        if ($markText !== '') {
            if (! is_numeric($markText)) {
                $errors[] = 'MARK must be numeric when supplied.';
            } else {
                $normalized = number_format((float) $markText, 2, '.', '');
                if ((float) $normalized < -9999.99 || (float) $normalized > 9999.99) {
                    $errors[] = 'MARK is outside the supported numeric range.';
                } else {
                    $mark = $normalized;
                }
            }
        }

        if ($mark !== null) {
            if ($statusText !== '') {
                $warnings[] = 'Mark is present together with candidate_status text; mark is accepted and source status is preserved.';
            }

            return [
                'mark' => $mark,
                'candidate_status' => 'active',
                'warnings' => $warnings,
                'errors' => $errors,
            ];
        }

        if ($markText !== '' && $errors !== []) {
            // A malformed mark is not silently converted to a cancellation.
            return [
                'mark' => null,
                'candidate_status' => 'cancelled',
                'warnings' => $warnings,
                'errors' => $errors,
            ];
        }

        if ($statusText === '') {
            $warnings[] = 'Both mark and candidate_status are blank; candidate is treated as cancelled and requires review.';
        }

        return [
            'mark' => null,
            'candidate_status' => 'cancelled',
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    private function plain(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_float($value) && floor($value) === $value) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }
}
