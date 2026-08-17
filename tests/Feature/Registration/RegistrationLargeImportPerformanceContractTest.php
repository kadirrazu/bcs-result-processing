<?php

namespace Tests\Feature\Registration;

use Tests\TestCase;

final class RegistrationLargeImportPerformanceContractTest extends TestCase
{
    public function test_large_registration_staging_uses_safe_adaptive_chunks_and_db_yield(): void
    {
        $config = file_get_contents(config_path('registrations.php'));
        $service = file_get_contents(app_path('Services/Registrations/RegistrationImportService.php'));
        $migration = file_get_contents(
            database_path('examination-migrations/2026_08_15_123000_optimize_registration_import_staging_indexes.php')
        );

        $this->assertStringContainsString(
            "REGISTRATION_LARGE_IMPORT_THRESHOLD', 100000",
            $config
        );

        $this->assertStringContainsString(
            "REGISTRATION_LARGE_STAGING_CHUNK_SIZE', 500",
            $config
        );

        $this->assertStringContainsString(
            "REGISTRATION_STAGING_THROTTLE_MS', 15",
            $config
        );

        $this->assertStringContainsString(
            'effectiveStagingChunkSize(',
            $service
        );

        $this->assertStringContainsString(
            '$totalRows >= $largeThreshold',
            $service
        );

        $this->assertStringContainsString(
            'min($requestedRows, $largeChunkSize)',
            $service
        );

        $this->assertStringContainsString(
            'yieldAfterStagingWrite()',
            $service
        );

        $this->assertStringContainsString(
            'usleep($milliseconds * 1000)',
            $service
        );

        $this->assertStringContainsString(
            "dropIndex('registration_import_staging_reg_index')",
            $migration
        );

        $this->assertStringContainsString(
            "dropIndex('registration_import_staging_user_id_index')",
            $migration
        );

        $this->assertStringNotContainsString(
            'dropUnique',
            $migration
        );
    }
}
