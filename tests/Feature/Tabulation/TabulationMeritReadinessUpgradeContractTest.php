<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

final class TabulationMeritReadinessUpgradeContractTest extends TestCase
{
    public function test_tabulation_snapshots_merit_tie_break_identity_and_hashes_finalized_dataset(): void
    {
        $migration = file_get_contents(base_path('database/examination-migrations/2026_08_13_220000_add_merit_readiness_contract_to_tabulation.php'));
        $generation = file_get_contents(app_path('Services/Tabulation/TabulationGenerationService.php'));
        $finalization = file_get_contents(app_path('Services/Tabulation/TabulationFinalizationService.php'));
        $hasher = file_get_contents(app_path('Services/Tabulation/TabulationDatasetHasher.php'));
        $dataset = file_get_contents(app_path('Services/Tabulation/TabulationFinalizedDatasetService.php'));

        $this->assertStringContainsString("'cadre_category'", $migration);
        $this->assertStringContainsString("'birth_date'", $migration);
        $this->assertStringContainsString("'dataset_hash'", $migration);

        $this->assertStringContainsString("'r.cadre_category','r.birth_date'", $generation);
        $this->assertStringContainsString("'MERIT_TIE_BREAK_BIRTH_DATE_MISSING'", $generation);
        $this->assertStringContainsString('TabulationDatasetHasher $datasetHasher', $generation);
        $this->assertStringContainsString("'dataset_hash'=>\$datasetHash", $generation);

        $this->assertStringContainsString('TABULATION_DATASET_HASH_MISMATCH', $finalization);
        $this->assertStringContainsString("'dataset_hash'=>\$currentHash", $finalization);
        $this->assertStringContainsString("'birth_date' => \$row->birth_date?->format('Y-m-d')", $hasher);
        $this->assertStringContainsString('hash_equals($storedHash, $currentHash)', $dataset);
    }

    public function test_tabulation_remains_independent_of_circular_and_choice_validation(): void
    {
        $generation = file_get_contents(app_path('Services/Tabulation/TabulationGenerationService.php'));
        $readiness = file_get_contents(app_path('Services/Tabulation/TabulationReadinessService.php'));

        $this->assertStringNotContainsString('CircularFinalizedDatasetService', $generation);
        $this->assertStringNotContainsString('ChoiceValidationFinalizedDatasetService', $generation);
        $this->assertStringNotContainsString('CircularFinalizedDatasetService', $readiness);
        $this->assertStringNotContainsString('ChoiceValidationFinalizedDatasetService', $readiness);
    }
}
