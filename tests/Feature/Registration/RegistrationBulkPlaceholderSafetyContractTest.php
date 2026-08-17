<?php

namespace Tests\Feature\Registration;

use Tests\TestCase;

final class RegistrationBulkPlaceholderSafetyContractTest extends TestCase
{
    public function test_registration_staging_and_merge_are_capped_below_mysql_placeholder_limit(): void
    {
        $config = file_get_contents(config_path('registrations.php'));
        $import = file_get_contents(app_path('Services/Registrations/RegistrationImportService.php'));
        $approval = file_get_contents(app_path('Services/Registrations/RegistrationApprovalService.php'));

        $this->assertStringContainsString("'bulk_placeholder_budget'", $config);
        $this->assertStringContainsString('REGISTRATION_BULK_PLACEHOLDER_BUDGET', $config);
        $this->assertStringContainsString("REGISTRATION_STAGING_CHUNK_SIZE', 1500", $config);
        $this->assertStringContainsString("REGISTRATION_LARGE_STAGING_CHUNK_SIZE', 500", $config);
        $this->assertStringContainsString("REGISTRATION_MERGE_CHUNK_SIZE', 1500", $config);

        $this->assertStringContainsString(
            '$stagingColumnCount = count($this->toStagingRow(',
            $import
        );
        $this->assertStringContainsString(
            '$chunkSize = $this->effectiveStagingChunkSize(',
            $import
        );
        $this->assertStringContainsString(
            'return $this->safeBulkWriteSize($effectiveRequestedRows, $columnCount);',
            $import
        );
        $this->assertStringContainsString(
            "bulk_placeholder_budget', 60000",
            $import
        );

        $this->assertStringContainsString(
            '$this->registrationMergeColumnCount()',
            $approval
        );
        $this->assertStringContainsString(
            'return 32;',
            $approval
        );
        $this->assertStringContainsString(
            "bulk_placeholder_budget', 60000",
            $approval
        );
    }
}
