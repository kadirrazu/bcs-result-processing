<?php

namespace Tests\Feature\Preliminary;

use Tests\TestCase;

final class PreliminaryProcessingStateSingletonResetContractTest extends TestCase
{
    public function test_preliminary_processing_state_remains_id_one_after_delete_based_reset(): void
    {
        $model = file_get_contents(
            app_path('Models/PreliminaryProcessingState.php')
        );
        $approval = file_get_contents(
            app_path('Services/Preliminary/PreliminaryApprovalService.php')
        );
        $controller = file_get_contents(
            app_path('Http/Controllers/PreliminaryController.php')
        );
        $repair = file_get_contents(
            database_path('examination-migrations/2026_08_19_165500_repair_preliminary_processing_state_singleton.php')
        );

        $this->assertStringContainsString(
            'public $incrementing = false;',
            $model
        );
        $this->assertStringContainsString(
            "'id',",
            $model
        );

        $this->assertStringContainsString(
            "updateOrCreate(\n                ['id' => 1]",
            $approval
        );
        $this->assertStringContainsString(
            "firstOrCreate(['id' => 1]",
            $controller
        );

        $this->assertStringContainsString(
            "\$payload['id'] = 1;",
            $repair
        );
        $this->assertStringContainsString(
            '$row->latest_import_batch_id !== null',
            $repair
        );
        $this->assertStringContainsString(
            "->table('preliminary_processing_states')",
            $repair
        );
    }
}
