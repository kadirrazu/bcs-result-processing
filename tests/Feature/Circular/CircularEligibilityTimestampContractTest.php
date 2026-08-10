<?php

namespace Tests\Feature\Circular;

use App\Models\CircularEntryBachelorSubject;
use App\Models\CircularEntryPrs;
use Tests\TestCase;

final class CircularEligibilityTimestampContractTest extends TestCase
{
    public function test_circular_eligibility_child_models_do_not_expect_timestamp_columns(): void
    {
        $this->assertFalse((new CircularEntryBachelorSubject)->usesTimestamps());
        $this->assertFalse((new CircularEntryPrs)->usesTimestamps());
    }
}
