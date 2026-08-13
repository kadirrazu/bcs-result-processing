<?php

namespace Tests\Feature\Circular;

use Tests\TestCase;

final class CircularFinalizedHashVerificationContractTest extends TestCase
{
    public function test_finalized_circular_dataset_is_rehashed_before_verified_downstream_consumption(): void
    {
        $workflow = file_get_contents(app_path('Services/Circular/CircularAuthorityWorkflowService.php'));
        $dataset = file_get_contents(app_path('Services/Circular/CircularFinalizedDatasetService.php'));
        $hasher = file_get_contents(app_path('Services/Circular/CircularDatasetHasher.php'));

        $this->assertStringContainsString('CircularDatasetHasher $datasetHasher', $workflow);
        $this->assertStringContainsString('return $this->datasetHasher->hash($version);', $workflow);
        $this->assertStringContainsString('CircularDatasetHasher $hasher', $dataset);
        $this->assertStringContainsString('hash_equals((string) $confirmation->dataset_hash, $currentHash)', $dataset);
        $this->assertStringContainsString('CIRCULAR_DATASET_HASH_MISMATCH', $dataset);
        $this->assertStringContainsString("return hash('sha256'", $hasher);
    }
}
