<?php

namespace App\Services\Tabulation;

use App\Models\PreliminaryResult;
use App\Models\TabulationResult;
use App\Models\VivaResult;
use App\Models\WrittenResult;

final class TabulationSourceDerivedVerificationService
{
    public function build(
        TabulationResult $result,
        ?PreliminaryResult $preliminary,
        WrittenResult $written,
        VivaResult $viva,
    ): array {
        return [
            $this->row('Preliminary Mark', $preliminary?->mark, $result->preliminary_mark),
            $this->row('General Written Total', $written->general_counted_total, $result->general_written_total),
            $this->row('Technical Written Total', $written->technical_counted_total, $result->technical_written_total),
            $this->row('Viva Mark', $viva->mark, $result->viva_mark),
            $this->row('General P/F', $this->statusValue($written->general_result_status), $result->general_pf),
            $this->row('Technical P/F', $this->statusValue($written->technical_result_status), $result->technical_pf),
        ];
    }

    private function row(string $label, mixed $source, mixed $derived): array
    {
        return [
            'label' => $label,
            'source' => $source,
            'derived' => $derived,
            'matches' => $this->normalize($source) === $this->normalize($derived),
        ];
    }

    private function normalize(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 4, '.', '');
        }

        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtoupper(trim((string) $value));

        return match ($normalized) {
            'PASS' => 'PASS',
            'FAIL' => 'FAIL',
            default => $normalized,
        };
    }

    private function statusValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return strtoupper((string) $value->value);
        }

        return $value === null ? null : strtoupper((string) $value);
    }
}
