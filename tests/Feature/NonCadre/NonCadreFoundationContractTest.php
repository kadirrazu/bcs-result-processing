<?php

namespace Tests\Feature\NonCadre;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class NonCadreFoundationContractTest extends TestCase
{
    #[Test]
    public function non_cadre_is_registered_after_reporting_as_an_isolated_module(): void
    {
        $navigation = file_get_contents(config_path('navigation.php'));
        $web = file_get_contents(base_path('routes/web.php'));
        $service = file_get_contents(app_path('Services/NonCadre/NonCadreReadinessService.php'));

        self::assertNotFalse($navigation);
        self::assertLessThan(strpos($navigation, "'label' => 'Non Cadre Processing'"), strpos($navigation, "'label' => 'Reporting'"));
        self::assertStringContainsString("require __DIR__.'/non-cadre.php';", $web);
        self::assertStringContainsString('AllocationA6ReadinessService', $service);
        self::assertStringNotContainsString('update(', $service);
        self::assertStringNotContainsString('delete(', $service);
    }

    #[Test]
    public function non_cadre_foundation_uses_exam_database_and_preserves_locked_choice_and_allocation_authorities(): void
    {
        $migration = file_get_contents(database_path('examination-migrations/2026_09_18_230000_create_non_cadre_processing_foundation.php'));
        $requirements = file_get_contents(base_path('docs/Non_Cadre_Processing_Locked_Requirements_v1.0.md'));
        $config = require config_path('non-cadre.php');

        self::assertStringContainsString("Schema::connection('exam')", $migration);
        self::assertStringContainsString("DB::connection('exam')", $migration);
        self::assertStringContainsString("'original_choices'", $migration);
        self::assertStringContainsString("'validated_choices'", $migration);
        self::assertStringContainsString("'effective_choices'", $migration);
        self::assertStringContainsString("'common_merit_position'", $migration);
        self::assertStringContainsString("'reallocate_cancelled_seat'", $migration);
        self::assertSame(20, $config['choice']['max_options']);
        self::assertStringContainsString('ACTIVE / WITHHELD / CANCELLED', $requirements);
    }
}
