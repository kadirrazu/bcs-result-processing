<?php

namespace Tests\Feature\Allocation;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AllocationA1FileConfiguredSettingsContractTest extends TestCase
{
    #[Test]
    public function allocation_settings_are_file_configured_and_frozen_as_a_snapshot(): void
    {
        $root = base_path();
        $config = file_get_contents($root.'/config/allocation.php');
        $service = file_get_contents($root.'/app/Services/Allocation/AllocationSettingsService.php');
        $view = file_get_contents($root.'/resources/views/allocation/index.blade.php');

        self::assertStringContainsString("'quota_priority' => ['CFF', 'EM', 'PHC']", $config);
        self::assertStringContainsString("'provisional_breakup_percentages'", $config);
        self::assertStringContainsString("'quota_breakup_minimum_total_posts' => 10", $config);

        self::assertStringContainsString('function currentConfig()', $service);
        self::assertStringContainsString('function currentConfigHash()', $service);
        self::assertStringContainsString('ALLOCATION_SETTINGS_CONFIG_CHANGED', $service);
        self::assertStringContainsString('ALLOCATION_SETTINGS_REFROZEN', $service);
        self::assertStringContainsString("'config_file' => 'config/allocation.php'", $service);

        // Latest polished UI intentionally omits punctuation from the label.
        self::assertStringContainsString('Quota Breakup Minimum Total Post', $view);
        self::assertStringContainsString('LOCKED RULE', $view);
        self::assertStringContainsString('config/allocation.php', $view);
        self::assertStringContainsString('php artisan config:clear', $view);
        self::assertStringNotContainsString('Small-cadre threshold:', $view);
    }
}
