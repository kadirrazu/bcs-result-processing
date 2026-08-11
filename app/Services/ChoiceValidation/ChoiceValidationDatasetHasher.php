<?php

namespace App\Services\ChoiceValidation;

use App\Models\ChoiceValidationResult;

final class ChoiceValidationDatasetHasher
{
    public function hash(int $validationVersion): string
    {
        $context = hash_init('sha256');

        ChoiceValidationResult::query()
            ->where('validation_version', $validationVersion)
            ->orderBy('registration_id')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($context): void {
                foreach ($rows as $row) {
                    $payload = [
                        'registration_id' => (int) $row->registration_id,
                        'reg' => (string) $row->reg,
                        'user_id' => (string) ($row->user_id ?? ''),
                        'source_version' => (int) $row->source_version,
                        'validation_version' => (int) $row->validation_version,
                        'circular_version' => (int) $row->circular_version,
                        'written_qualified_track' => $row->written_qualified_track,
                        'effective_track' => $row->effective_track,
                        'status' => (string) $row->status,
                        'result_reason_code' => $row->result_reason_code,
                        'validated_choice_codes' => array_values((array) $row->validated_choice_codes),
                        'original_choice_count' => (int) $row->original_choice_count,
                        'validated_choice_count' => (int) $row->validated_choice_count,
                        'removed_choice_count' => (int) $row->removed_choice_count,
                        'expanded_choice_count' => (int) $row->expanded_choice_count,
                        'eligibility_snapshot' => $this->canonicalize((array) $row->eligibility_snapshot),
                    ];

                    hash_update(
                        $context,
                        json_encode(
                            $payload,
                            JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                            | JSON_THROW_ON_ERROR
                        )."\n"
                    );
                }
            }, 'id');

        return hash_final($context);
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonicalize($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
