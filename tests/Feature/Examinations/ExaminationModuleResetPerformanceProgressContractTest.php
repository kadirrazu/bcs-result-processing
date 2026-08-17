<?php

namespace Tests\Feature\Examinations;

use Tests\TestCase;

final class ExaminationModuleResetPerformanceProgressContractTest extends TestCase
{
    public function test_large_module_reset_uses_chunked_deletes_and_cli_progress_without_truncate(): void
    {
        $command = file_get_contents(
            app_path('Console/Commands/ResetExaminationModule.php')
        );
        $config = file_get_contents(
            config_path('development-module-reset.php')
        );

        $this->assertStringContainsString(
            'EXAMINATION_RESET_DELETE_CHUNK_SIZE',
            $config
        );
        $this->assertStringContainsString(
            "'delete_chunk_size'",
            $config
        );

        $this->assertStringContainsString(
            'createProgressBar(max(1, $totalRows))',
            $command
        );
        $this->assertStringContainsString(
            'After confirmation, a row-based progress bar will show reset progress.',
            $command
        );
        $this->assertStringContainsString(
            '->limit($chunkSize)',
            $command
        );
        $this->assertStringContainsString(
            '->delete()',
            $command
        );
        $this->assertStringContainsString(
            '$progress->advance($affected)',
            $command
        );

        $this->assertStringNotContainsString(
            'truncate(',
            strtolower($command)
        );
        $this->assertStringNotContainsString(
            'foreign_key_checks',
            strtolower($command)
        );
        $this->assertStringNotContainsString(
            '$connection->transaction(function () use (',
            $command
        );
    }
}
