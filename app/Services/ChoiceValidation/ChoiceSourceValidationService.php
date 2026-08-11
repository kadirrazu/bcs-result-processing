<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceValidationImportBatch;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ChoiceSourceValidationService
{
    public function __construct(private readonly ChoiceRowRuleValidator $rowRules) {}

    public function validate(int $batchId): ChoiceValidationImportBatch
    {
        $batch = ChoiceValidationImportBatch::query()->findOrFail($batchId);

        if (! in_array($batch->status, ['staged', 'validation_queued', 'validated', 'validation_failed'], true)) {
            throw new RuntimeException('Only a staged Choice source batch can be validated.');
        }

        $batch->update([
            'status' => 'validating',
            'processed_rows' => 0,
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'progress_percent' => 0,
            'failure_message' => null,
            'validated_at' => null,
            'finished_at' => null,
        ]);

        try {
            $total = (int) $batch->stagingRows()->count();
            $processed = 0;
            $valid = 0;
            $invalid = 0;
            $seenRegistrations = [];
            $chunkSize = max(100, (int) config('choice-validation.validation_chunk_size', 1000));

            $batch->stagingRows()->orderBy('id')->chunkById($chunkSize, function ($rows) use (
                $batch,
                $total,
                &$processed,
                &$valid,
                &$invalid,
                &$seenRegistrations,
            ): void {
                foreach ($rows as $row) {
                    $raw = is_array($row->raw_payload)
                        ? $row->raw_payload
                        : (json_decode((string) $row->raw_payload, true) ?: []);

                    $rule = $this->rowRules->validate($raw, (int) $batch->configured_maximum_choices);
                    $identityErrors = [];
                    $user = $this->normalizeIdentity($raw['user'] ?? null);
                    $reg = $this->normalizeIdentity($raw['reg'] ?? null);
                    $registrationId = null;

                    if ($user === null || $reg === null) {
                        $identityErrors[] = 'REGISTRATION_IDENTITY_MISMATCH: user and reg are both required.';
                    } else {
                        $match = DB::connection('exam')
                            ->table('registrations')
                            ->select(['id', 'user_id', 'reg'])
                            ->where('user_id', $user)
                            ->where('reg', $reg)
                            ->first();

                        if ($match === null) {
                            $regExists = DB::connection('exam')->table('registrations')->where('reg', $reg)->exists();
                            $userExists = DB::connection('exam')->table('registrations')->where('user_id', $user)->exists();
                            $identityErrors[] = ($regExists || $userExists)
                                ? 'REGISTRATION_IDENTITY_MISMATCH: user and reg do not identify the same Registration candidate.'
                                : 'REGISTRATION_NOT_FOUND: user/reg does not exist in Registration.';
                        } else {
                            $registrationId = (int) $match->id;
                            if (isset($seenRegistrations[$registrationId])) {
                                $identityErrors[] = 'DUPLICATE_SOURCE_CANDIDATE: the same Registration candidate appears more than once in this Choice source file.';
                            } else {
                                $seenRegistrations[$registrationId] = true;
                            }
                        }
                    }

                    $errors = array_values(array_unique([...$identityErrors, ...$rule['errors']]));
                    $status = $errors === [] ? 'valid' : 'invalid';
                    $status === 'valid' ? $valid++ : $invalid++;

                    $choiceMap = [];
                    foreach ($rule['choices'] as $choice) {
                        $choiceMap[$choice['column']] = $choice['raw'];
                    }

                    $row->update([
                        'registration_id' => $registrationId,
                        'user_id' => $user,
                        'reg' => $reg,
                        'raw_choices' => $choiceMap,
                        'raw_choice_count' => $rule['choice_count'],
                        'validation_status' => $status,
                        'validation_errors' => $errors === [] ? null : $errors,
                        'validation_warnings' => $rule['warnings'] === [] ? null : $rule['warnings'],
                    ]);

                    $processed++;
                }

                $batch->update([
                    'processed_rows' => $processed,
                    'valid_rows' => $valid,
                    'invalid_rows' => $invalid,
                    'progress_percent' => $total > 0 ? round(($processed / $total) * 100, 4) : 100,
                ]);
            });

            $batch->update([
                'status' => 'validated',
                'total_rows' => $total,
                'processed_rows' => $processed,
                'valid_rows' => $valid,
                'invalid_rows' => $invalid,
                'progress_percent' => 100,
                'validated_at' => now(),
                'finished_at' => now(),
            ]);

            return $batch->refresh();
        } catch (Throwable $e) {
            $batch->update([
                'status' => 'validation_failed',
                'failure_message' => mb_substr($e->getMessage(), 0, 65000),
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }

    private function rawText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function normalizeIdentity(mixed $value): ?string
    {
        $value = $this->rawText($value);
        if ($value !== null && preg_match('/^\d+\.0$/', $value) === 1) {
            $value = substr($value, 0, -2);
        }
        return $value;
    }
}
