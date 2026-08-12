<?php

namespace Tests\Feature;

use App\Services\Tabulation\TabulationSourceSnapshotComparator;
use Tests\TestCase;

class TabulationSourceSnapshotComparisonContractTest extends TestCase
{
    public function test_snapshot_comparison_ignores_json_object_key_order(): void
    {
        $service = app(TabulationSourceSnapshotComparator::class);

        $queued = [
            'registration' => ['count' => 3631, 'latest_approved_batch_id' => 4],
            'viva' => ['finalization_run_id' => 2, 'appeared_count' => 3200],
        ];

        $reloaded = [
            'viva' => ['appeared_count' => 3200, 'finalization_run_id' => 2],
            'registration' => ['latest_approved_batch_id' => 4, 'count' => 3631],
        ];

        $this->assertTrue($service->equivalent($queued, $reloaded));
        $reloaded['viva']['appeared_count'] = 3199;
        $this->assertFalse($service->equivalent($queued, $reloaded));
    }
}
