<?php

namespace Tests\Feature\Tabulation;

use Tests\TestCase;

class TabulationDatasetHashUiContractTest extends TestCase
{
    public function test_hash_is_exposed_without_full_rehash_on_normal_tabulation_page_load(): void
    {
        $service = file_get_contents(app_path('Services/Tabulation/TabulationDatasetIntegrityService.php'));
        $controller = file_get_contents(app_path('Http/Controllers/TabulationController.php'));
        $index = file_get_contents(resource_path('views/tabulation/index.blade.php'));
        $results = file_get_contents(resource_path('views/tabulation/results.blade.php'));
        $finalizedDataset = file_get_contents(app_path('Services/Tabulation/TabulationFinalizedDatasetService.php'));

        $this->assertStringContainsString('TabulationDatasetHasher $hasher', $service);
        $this->assertStringContainsString('HASH_VERIFIED_AT_FINALIZATION', $service);
        $this->assertStringContainsString("'HASH_VERIFIED'", $service);
        $this->assertStringContainsString("'HASH_MISMATCH'", $service);
        $this->assertStringContainsString('$this->hasher->hash', $service);

        $this->assertStringContainsString('TabulationDatasetIntegrityService $integrity', $controller);
        $this->assertStringContainsString("'hashIntegrity' => \$integrity->inspect(null, false)", $controller);
        $this->assertStringContainsString("'hashIntegrity' => \$integrity->inspect((int) \$run->id, false)", $controller);

        $this->assertStringContainsString('Finalized Dataset Integrity', $index);
        $this->assertStringContainsString('Finalized Dataset Hash (SHA-256)', $index);
        $this->assertStringContainsString("\$hashIntegrity['status']", $index);
        $this->assertStringContainsString('Finalized Dataset Integrity', $results);

        // Fresh comparison remains mandatory at downstream readiness gates.
        $this->assertStringContainsString('$this->hasher->hash', $finalizedDataset);
        $this->assertStringContainsString('TABULATION_DATASET_HASH_MISMATCH', $finalizedDataset);
    }
}
