<?php

namespace App\Services\ChoiceOptimization;

use App\Models\ChoiceOptimizationEffectiveChoice;
use App\Services\ChoiceValidation\ChoiceValidationFinalizedDatasetService;
use RuntimeException;

final class ChoiceOptimizationHistoricalInputService
{
    public function __construct(
        private readonly ChoiceValidationFinalizedDatasetService $choiceValidation,
    ) {}

    /**
     * @return array{
     *   rows: array<int,array{registration_id:int,reg:string,source:string,codes:array<int,string>}>,
     *   source:string,
     *   source_hash:string,
     *   choice_validation_version:int,
     *   choice_validation_hash:string
     * }
     */
    public function snapshot(): array
    {
        $summary = $this->choiceValidation->verifiedSummary();
        $choiceRows = $this->choiceValidation->choiceReadyResults();

        $effectiveRows = ChoiceOptimizationEffectiveChoice::query()
            ->orderBy('registration_id')
            ->get()
            ->keyBy('registration_id');

        $useEffective = $effectiveRows->isNotEmpty();

        if ($useEffective && $effectiveRows->count() !== $choiceRows->count()) {
            throw new RuntimeException(
                'Choice Optimization effective choice snapshot is incomplete. Re-approve the OMR/effective-choice stage before Historical Choice Optimization.'
            );
        }

        $rows = [];
        foreach ($choiceRows as $choiceRow) {
            $registrationId = (int) $choiceRow->registration_id;

            if ($useEffective) {
                $effective = $effectiveRows->get($registrationId);
                if (! $effective) {
                    throw new RuntimeException(
                        "Effective choice row is missing for registration {$choiceRow->reg}. Re-approve the OMR/effective-choice stage."
                    );
                }

                $codes = $this->cleanCodes((array) $effective->effective_choice_codes);
                $source = (string) $effective->choice_source;
            } else {
                $codes = $this->cleanCodes((array) $choiceRow->validated_choice_codes);
                $source = 'finalized_validated_choice';
            }

            $rows[] = [
                'registration_id' => $registrationId,
                'reg' => (string) $choiceRow->reg,
                'source' => $source,
                'codes' => $codes,
            ];
        }

        $sourceHash = $this->hashRows($rows);

        return [
            'rows' => $rows,
            'source' => $useEffective ? 'choice_optimization_effective_choice' : 'finalized_validated_choice',
            'source_hash' => $sourceHash,
            'choice_validation_version' => (int) ($summary['validation_version'] ?? 0),
            'choice_validation_hash' => (string) ($summary['dataset_hash'] ?? ''),
        ];
    }

    /** @param array<int,array{registration_id:int,reg:string,source:string,codes:array<int,string>}> $rows */
    public function hashRows(array $rows): string
    {
        $context = hash_init('sha256');

        foreach ($rows as $row) {
            hash_update($context, json_encode([
                'registration_id' => $row['registration_id'],
                'reg' => $row['reg'],
                'source' => $row['source'],
                'codes' => array_values($row['codes']),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
        }

        return hash_final($context);
    }

    /** @return array<int,string> */
    private function cleanCodes(array $codes): array
    {
        return array_values(array_filter(
            array_map(static fn ($code): string => trim((string) $code), $codes),
            static fn (string $code): bool => $code !== '',
        ));
    }
}
