<?php

namespace App\Services\ChoiceValidation;

use App\Enums\ChoiceValidationReason;
use RuntimeException;

final class ChoiceColumnResolver
{
    public function maximumAllowedChoices(): int
    {
        return $this->assertMaximum((int) config('choice-validation.maximum_allowed_choices', 20));
    }

    /** @return list<string> */
    public function choiceColumns(?int $maximum = null): array
    {
        $maximum = $this->assertMaximum($maximum ?? $this->maximumAllowedChoices());
        return array_map(fn (int $position): string => $this->columnForPosition($position), range(1, $maximum));
    }

    /** @return list<string> */
    public function expectedHeaders(?int $maximum = null): array
    {
        return ['user', 'reg', ...$this->choiceColumns($maximum)];
    }

    public function columnForPosition(int $position): string
    {
        return 'opt_'.str_pad((string) $position, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param list<mixed> $rawHeaders
     * @return list<string>
     */
    public function validateHeaders(array $rawHeaders, ?int $maximum = null): array
    {
        $maximum = $this->assertMaximum($maximum ?? $this->maximumAllowedChoices());
        $headers = array_values(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            $rawHeaders,
        ));

        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        foreach ($headers as $header) {
            if (preg_match('/^opt_(\d+)$/', $header, $matches) !== 1) {
                continue;
            }

            if ((int) $matches[1] > $maximum) {
                throw new RuntimeException(sprintf(
                    '%s: spreadsheet contains [%s], but the configured maximum allowed choices for this import is %d.',
                    ChoiceValidationReason::ChoiceExceedsMaximumAllowedLimit->value,
                    $header,
                    $maximum,
                ));
            }
        }

        $expected = $this->expectedHeaders($maximum);
        if ($headers !== $expected) {
            throw new RuntimeException(sprintf(
                'Choice spreadsheet headers do not match the configured template. Expected [%s]. Found [%s].',
                implode(', ', $expected),
                implode(', ', $headers),
            ));
        }

        return $headers;
    }

    private function assertMaximum(int $maximum): int
    {
        if ($maximum < 1) {
            throw new RuntimeException('choice-validation.maximum_allowed_choices must be at least 1.');
        }
        return $maximum;
    }
}
