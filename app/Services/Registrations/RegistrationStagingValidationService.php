<?php

namespace App\Services\Registrations;

use App\Enums\CadreCategory;
use App\Enums\RegistrationStatus;
use App\Models\RegistrationImportBatch;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/** Validate staged rows in large set-oriented chunks without touching main registrations. */
final class RegistrationStagingValidationService
{
    public function __construct(private readonly RegistrationMasterMap $maps) {}

    public function validate(int $batchId): RegistrationImportBatch
    {
        $batch = RegistrationImportBatch::query()->findOrFail($batchId);
        if (! in_array($batch->status, ['staged', 'validation_queued', 'validated', 'failed'], true)) {
            throw new RuntimeException('Only a staged or retryable validation batch can be validated.');
        }

        /*
         * A failed validation job leaves all staged rows intact. Allowing a retry
         * avoids re-uploading and re-staging a 400K-row workbook after a transient
         * SQL or configuration failure.
         */
        if ($batch->status === 'failed' && (int) $batch->staged_rows < 1) {
            throw new RuntimeException('This failed batch has no staged rows to validate.');
        }

        $batch->update([
            'status' => 'validating',
            'progress_percent' => 0,
            'processed_rows' => 0,
            'failure_message' => null,
            'heartbeat_at' => now(),
        ]);

        try {
            $masters = $this->maps->load();
            $duplicateRegs = $this->duplicateValues($batchId, 'reg');
            $duplicateUsers = $this->duplicateValues($batchId, 'user_id');
            $chunkSize = max(500, (int) config('registrations.validation_chunk_size', 5000));
            $processed = 0;
            $valid = 0;
            $warnings = 0;
            $invalid = 0;
            $conflicts = 0;
            $total = (int) $batch->staged_rows;

            DB::connection('exam')->table('registration_import_staging')
                ->where('batch_id', $batchId)
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (
                    $batch, $masters, $duplicateRegs, $duplicateUsers,
                    &$processed, &$valid, &$warnings, &$invalid, &$conflicts, $total
                ): void {
                    $regs = [];
                    $users = [];
                    foreach ($rows as $row) {
                        if ($row->reg !== null && $row->reg !== '') {
                            $regs[] = (string) $row->reg;
                        }
                        if ($row->user_id !== null && $row->user_id !== '') {
                            $users[] = (string) $row->user_id;
                        }
                    }

                    $existing = DB::connection('exam')->table('registrations')
                        ->where(function ($query) use ($regs, $users): void {
                            if ($regs !== []) {
                                $query->whereIn('reg', array_values(array_unique($regs)));
                            }
                            if ($users !== []) {
                                $query->{$regs === [] ? 'whereIn' : 'orWhereIn'}('user_id', array_values(array_unique($users)));
                            }
                        })
                        ->get(['id', 'reg', 'user_id']);

                    $byReg = $existing->keyBy('reg');
                    $byUser = $existing->keyBy('user_id');
                    $updates = [];
                    $timestamp = now()->format('Y-m-d H:i:s');

                    foreach ($rows as $row) {
                        $errors = [];
                        $rowWarnings = [];
                        $reg = trim((string) ($row->reg ?? ''));
                        $userId = strtoupper(trim((string) ($row->user_id ?? '')));
                        $name = trim((string) ($row->name ?? ''));
                        $category = $this->nullableInt($row->cadre_category);
                        $status = strtolower(trim((string) ($row->candidate_status ?? 'active')));
                        $postRelated = $this->nullableInt($row->post_related_subject_code);
                        $district = $this->nullableInt($row->district_code);
                        $university = $this->nullableInt($row->university_code);
                        $bachelor = $this->nullableInt($row->bachelor_subject_code);
                        $sex = $this->nullableInt($row->sex_code);
                        $sscRoll = $this->nullableText($row->ssc_roll);
                        $hscRoll = $this->nullableText($row->hsc_roll);
                        $sscYear = $this->nullableYear($row->ssc_year, 'SSC_YEAR', $errors);
                        $hscYear = $this->nullableYear($row->hsc_year, 'HSC_YEAR', $errors);
                        $graduationYear = $this->nullableYear($row->graduation_year, 'GRADUATION_YEAR', $errors);

                        if (! preg_match('/^[A-Z0-9]{1,10}$/', $userId)) {
                            $errors[] = 'USER must be alphanumeric and at most 10 characters.';
                        }
                        if (! preg_match('/^\d{1,8}$/', $reg)) {
                            $errors[] = 'REG must contain at most 8 digits.';
                        }
                        if ($name === '') {
                            $errors[] = 'NAME is required.';
                        }
                        if (isset($duplicateRegs[$reg])) {
                            $errors[] = 'REG appears more than once in this import batch.';
                        }
                        if (isset($duplicateUsers[$userId])) {
                            $errors[] = 'USER appears more than once in this import batch.';
                        }
                        if (! in_array($category, CadreCategory::values(), true)) {
                            $errors[] = 'CADRE_CATEGORY must be 1, 2 or 3.';
                        }
                        if (! in_array($status, RegistrationStatus::values(), true)) {
                            $errors[] = 'STATUS must be active, cancelled or withheld.';
                        }

                        $birthDate = $this->parseDdMmYyyy($row->raw_birth_date);
                        if ($row->raw_birth_date !== null && trim((string) $row->raw_birth_date) !== '' && $birthDate === null) {
                            $errors[] = 'B_DATE must be a valid DDMMYYYY date.';
                        }

                        foreach ([
                            [$sex, 'sex', 'SEX'],
                            [$district, 'district', 'DISTRICT'],
                            [$bachelor, 'b_subject', 'B_SUBJECT'],
                            [$postRelated, 'post_related_subject', 'POST_RELATED_SUBJECT'],
                        ] as [$code, $map, $label]) {
                            if ($code !== null && ! isset($masters[$map][(string) $code])) {
                                $errors[] = "Unknown {$label} code [{$code}].";
                            }
                        }

                        if ($university !== null && ! isset($masters['university'][(string) $university])) {
                            $rowWarnings[] = "Invalid University Code: {$university}";
                        }

                        if ($category === CadreCategory::General->value && $postRelated !== null) {
                            $postRelated = null;
                            $rowWarnings[] = 'GG candidate supplied POST_RELATED_SUBJECT; value normalized to NULL.';
                        }
                        if (in_array($category, [CadreCategory::Technical->value, CadreCategory::GeneralAndTechnical->value], true)
                            && $postRelated === null) {
                            $errors[] = 'POST_RELATED_SUBJECT is required for TT and GT candidates.';
                        }

                        $regMatch = $reg !== '' ? $byReg->get($reg) : null;
                        $userMatch = $userId !== '' ? $byUser->get($userId) : null;
                        $identityConflict = ($regMatch && $regMatch->user_id !== $userId)
                            || ($userMatch && $userMatch->reg !== $reg)
                            || ($regMatch && $userMatch && $regMatch->id !== $userMatch->id);

                        if ($identityConflict) {
                            $errors[] = 'REG and USER identify different existing candidates.';
                            $conflicts++;
                        }

                        $validationStatus = $errors !== [] ? 'invalid' : ($rowWarnings !== [] ? 'warning' : 'valid');
                        if ($validationStatus === 'invalid') {
                            $invalid++;
                        } elseif ($validationStatus === 'warning') {
                            $warnings++;
                        } else {
                            $valid++;
                        }

                        $division = $district === null
                            ? null
                            : ($masters['district_division'][(string) $district] ?? null);

                        $updates[] = [
                            /*
                             * Laravel compiles upsert as INSERT ... ON DUPLICATE KEY UPDATE.
                             * Required staging identity columns must therefore be present even
                             * though the row already exists and only validation fields change.
                             */
                            'id' => $row->id,
                            'batch_id' => $row->batch_id,
                            'source_row' => $row->source_row,
                            'user_id' => $userId,
                            'reg' => $reg,
                            'birth_date' => $birthDate,
                            'ssc_roll' => $sscRoll,
                            'ssc_year' => $sscYear,
                            'hsc_roll' => $hscRoll,
                            'hsc_year' => $hscYear,
                            'graduation_year' => $graduationYear,
                            'sex_code' => $sex,
                            'district_code' => $district,
                            'division_code' => $division,
                            'university_code' => $university,
                            'bachelor_subject_code' => $bachelor,
                            'post_related_subject_code' => $postRelated,
                            'cadre_category' => $category,
                            'has_ff_quota' => $this->nullableInt($row->has_ff_quota),
                            'has_em_quota' => $this->nullableInt($row->has_em_quota),
                            'has_phc_quota' => $this->nullableInt($row->has_phc_quota),
                            'has_quota' => $this->hasQuota($row->has_ff_quota, $row->has_em_quota, $row->has_phc_quota),
                            'candidate_status' => $status,
                            'validation_status' => $validationStatus,
                            'validation_errors' => $errors === [] ? null : json_encode(array_values(array_unique($errors)), JSON_UNESCAPED_UNICODE),
                            'validation_warnings' => $rowWarnings === [] ? null : json_encode(array_values(array_unique($rowWarnings)), JSON_UNESCAPED_UNICODE),
                            'updated_at' => $timestamp,
                        ];
                    }

                    $updateColumns = [
                        'user_id', 'reg', 'birth_date', 'ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'graduation_year',
                        'sex_code', 'district_code', 'division_code',
                        'university_code', 'bachelor_subject_code', 'post_related_subject_code',
                        'cadre_category', 'has_ff_quota', 'has_em_quota', 'has_phc_quota',
                        'has_quota', 'candidate_status', 'validation_status', 'validation_errors',
                        'validation_warnings', 'updated_at',
                    ];

                    /*
                     * MySQL prepared statements support at most 65,535 placeholders.
                     * Each staging row currently binds 20 values, so a 5,000-row
                     * upsert exceeds that limit. Split the write into independently
                     * safe batches even when the validation read chunk is larger.
                     */
                    $safeWriteSize = max(100, min(
                        (int) config('registrations.validation_write_chunk_size', 2000),
                        intdiv(60000, max(1, count($updates[0] ?? []))),
                    ));

                    foreach (array_chunk($updates, $safeWriteSize) as $writeBatch) {
                        DB::connection('exam')->table('registration_import_staging')->upsert(
                            $writeBatch,
                            ['id'],
                            $updateColumns,
                        );
                    }

                    $processed += count($updates);
                    $batch->update([
                        'processed_rows' => $processed,
                        'progress_percent' => $total > 0 ? min(100, round(($processed / $total) * 100, 4)) : 0,
                        'valid_rows' => $valid,
                        'warning_rows' => $warnings,
                        'invalid_rows' => $invalid,
                        'failed_rows' => $invalid,
                        'identity_conflict_rows' => $conflicts,
                        'heartbeat_at' => now(),
                    ]);
                });

            $batch->update([
                'status' => 'validated',
                'processed_rows' => $processed,
                'progress_percent' => 100,
                'valid_rows' => $valid,
                'warning_rows' => $warnings,
                'invalid_rows' => $invalid,
                'failed_rows' => $invalid,
                'identity_conflict_rows' => $conflicts,
                'validated_at' => now(),
                'heartbeat_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $exception) {
            $batch->update([
                'status' => 'failed',
                'failure_message' => mb_substr($exception->getMessage(), 0, 65000),
                'heartbeat_at' => now(),
            ]);
            throw $exception;
        }
    }

    /** @return array<string,true> */
    private function duplicateValues(int $batchId, string $column): array
    {
        return DB::connection('exam')->table('registration_import_staging')
            ->where('batch_id', $batchId)
            ->whereNotNull($column)
            ->where($column, '<>', '')
            ->select($column)
            ->groupBy($column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck($column)
            ->mapWithKeys(static fn ($value): array => [(string) $value => true])
            ->all();
    }

    private function nullableInt(mixed $value): ?int
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : null;
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /** @param list<string> $errors */
    private function nullableYear(mixed $value, string $label, array &$errors): ?int
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^\d{4}$/', $raw) !== 1) {
            $errors[] = "{$label} must be a four-digit year.";
            return null;
        }

        $year = (int) $raw;
        if ($year < 1900 || $year > ((int) date('Y') + 1)) {
            $errors[] = "{$label} must be between 1900 and next calendar year.";
            return null;
        }

        return $year;
    }

    /**
     * Parse the source DDMMYYYY value without losing dates whose day starts with zero.
     *
     * Excel frequently stores an apparently eight-character value such as 05081995
     * as the number 5081995. OpenSpout then returns that seven-digit value because
     * numeric cells cannot preserve a leading zero. We restore only that missing
     * day-position zero before applying strict calendar validation.
     */
    private function parseDdMmYyyy(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            return null;
        }

        // Some spreadsheet readers expose an integer-looking cell as "5081995.0".
        if (preg_match('/^(\d+)\.0+$/', $raw, $matches) === 1) {
            $raw = $matches[1];
        }

        // A seven-digit source means Excel removed the leading zero from DDMMYYYY.
        if (preg_match('/^\d{7}$/', $raw) === 1) {
            $raw = '0'.$raw;
        }

        if (preg_match('/^\d{8}$/', $raw) !== 1) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!dmY', $raw);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    private function hasQuota(mixed ...$values): bool
    {
        foreach ($values as $value) {
            $number = $this->nullableInt($value);
            if ($number !== null && $number > 0) {
                return true;
            }
        }

        return false;
    }
}
