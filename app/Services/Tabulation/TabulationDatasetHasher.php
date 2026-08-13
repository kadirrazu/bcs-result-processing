<?php

namespace App\Services\Tabulation;

use App\Models\TabulationResult;

final class TabulationDatasetHasher
{
    public function hash(int $processingRunId): string
    {
        $context = hash_init('sha256');

        TabulationResult::query()
            ->where('processing_run_id', $processingRunId)
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($context): void {
                foreach ($rows as $row) {
                    $payload = [
                        'processing_version' => (int) $row->processing_version,
                        'registration_id' => (int) $row->registration_id,
                        'preliminary_result_id' => $row->preliminary_result_id === null ? null : (int) $row->preliminary_result_id,
                        'written_result_id' => (int) $row->written_result_id,
                        'viva_result_id' => (int) $row->viva_result_id,
                        'user_id' => (string) $row->user_id,
                        'reg' => (string) $row->reg,
                        'cadre_category' => $row->cadre_category === null ? null : (int) $row->cadre_category,
                        'birth_date' => $row->birth_date?->format('Y-m-d'),
                        'written_qualified_track' => $row->written_qualified_track,
                        'preliminary_mark' => $row->preliminary_mark === null ? null : (string) $row->preliminary_mark,
                        'general_written_total' => $row->general_written_total === null ? null : (string) $row->general_written_total,
                        'technical_written_total' => $row->technical_written_total === null ? null : (string) $row->technical_written_total,
                        'viva_mark' => (string) $row->viva_mark,
                        'general_grand_total' => $row->general_grand_total === null ? null : (string) $row->general_grand_total,
                        'technical_grand_total' => $row->technical_grand_total === null ? null : (string) $row->technical_grand_total,
                        'general_pf' => (string) $row->general_pf,
                        'technical_pf' => (string) $row->technical_pf,
                        'general_merit_eligible' => (bool) $row->general_merit_eligible,
                        'technical_merit_eligible' => (bool) $row->technical_merit_eligible,
                        'validation_status' => (string) $row->validation_status,
                        'validation_errors' => $this->canonicalize((array) $row->validation_errors),
                        'review_warnings' => $this->canonicalize((array) $row->review_warnings),
                        'source_snapshot' => $this->canonicalize((array) $row->source_snapshot),
                        'processing_flags' => $this->canonicalize((array) $row->processing_flags),
                    ];

                    hash_update(
                        $context,
                        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n"
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
