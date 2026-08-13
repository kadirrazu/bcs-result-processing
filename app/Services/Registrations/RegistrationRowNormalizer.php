<?php

namespace App\Services\Registrations;

use DateTimeImmutable;
use DateTimeInterface;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/** Normalize one source row without database access. */
final class RegistrationRowNormalizer
{
    public function __construct(
        private readonly RegistrationBusinessRuleNormalizer $businessRules,
        private readonly RegistrationQuotaResolver $quotaResolver,
    ) {}

    /**
     * @param array<string, mixed> $row
     * @return array{data: array<string, mixed>, warnings: list<string>}
     */
    public function normalize(array $row, int $batchId): array
    {
        $ff = $this->nullableInt($row['has_ff_quota'] ?? null);
        $em = $this->nullableInt($row['has_em_quota'] ?? null);
        $phc = $this->nullableInt($row['has_phc_quota'] ?? null);

        $data = [
            'user_id' => strtoupper($this->text($row['user'] ?? null)),
            'reg' => $this->text($row['reg'] ?? null),
            'national_id' => $this->nullableText($row['national_id'] ?? null),
            'name' => $this->text($row['name'] ?? null),
            'father_name' => $this->nullableText($row['fname'] ?? null),
            'mother_name' => $this->nullableText($row['mname'] ?? null),
            'name_bn' => $this->nullableText($row['name_bn'] ?? null),
            'father_name_bn' => $this->nullableText($row['fname_bn'] ?? null),
            'mother_name_bn' => $this->nullableText($row['mname_bn'] ?? null),
            'birth_date' => $this->date($row['b_date'] ?? null),
            'ssc_roll' => $this->nullableText($row['ssc_roll'] ?? null),
            'ssc_year' => $this->nullableInt($row['ssc_year'] ?? null),
            'hsc_roll' => $this->nullableText($row['hsc_roll'] ?? null),
            'hsc_year' => $this->nullableInt($row['hsc_year'] ?? null),
            'graduation_year' => $this->nullableInt($row['graduation_year'] ?? null),
            'sex_code' => $this->nullableInt($row['sex'] ?? null),
            'district_code' => $this->nullableInt($row['district'] ?? null),
            'division_code' => null,
            'university_code' => $this->nullableInt($row['university'] ?? null),
            'bachelor_subject_code' => $this->nullableInt($row['b_subject'] ?? null),
            'post_related_subject_code' => $this->nullableInt($row['post_related_subject'] ?? null),
            'cadre_category' => $this->nullableInt($row['cadre_category'] ?? null),
            'has_ff_quota' => $ff,
            'has_em_quota' => $em,
            'has_phc_quota' => $phc,
            'has_quota' => $this->quotaResolver->hasQuota($ff, $em, $phc),
            'status' => strtolower($this->nullableText($row['status'] ?? null) ?? 'active'),
            'validation_status' => 'valid',
            'comment' => $this->nullableText($row['comment'] ?? null),
            'source_batch_id' => $batchId,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return $this->businessRules->normalize($data);
    }

    private function text(mixed $value): string
    {
        return trim((string) $value);
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = $this->nullableText($value);

        return $value !== null && is_numeric($value) ? (int) $value : null;
    }

    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        // Eight numeric digits represent DDMMYYYY, not an Excel serial number.
        if (preg_match('/^\d{8}$/', $raw) === 1) {
            $date = $this->strictDate('dmY', $raw);
            if ($date !== null) {
                return $date;
            }
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y'] as $format) {
            $date = $this->strictDate($format, $raw);
            if ($date !== null) {
                return $date;
            }
        }

        if (is_numeric($value)) {
            $serial = (float) $value;
            if ($serial >= 1 && $serial <= 100000) {
                try {
                    return ExcelDate::excelToDateTimeObject($serial)->format('Y-m-d');
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return null;
    }

    private function strictDate(string $format, string $value): ?string
    {
        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false) {
            return null;
        }

        if ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return null;
        }

        return $date->format('Y-m-d');
    }
}
