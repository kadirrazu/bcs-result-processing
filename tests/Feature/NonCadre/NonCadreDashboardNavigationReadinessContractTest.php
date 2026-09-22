<?php

namespace Tests\Feature\NonCadre;

use Tests\TestCase;

final class NonCadreDashboardNavigationReadinessContractTest extends TestCase
{
    public function test_dashboard_configures_selected_exam_before_non_cadre_navigation_readiness_is_evaluated(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $middleware = file_get_contents(app_path('Http/Middleware/ConfigureSelectedExaminationConnection.php'));
        $navigation = file_get_contents(resource_path('views/layouts/partials/examination-navigation.blade.php'));

        self::assertStringContainsString('ConfigureSelectedExaminationConnection::class', $routes);
        self::assertStringContainsString("Route::view('/dashboard', 'dashboard.index')", $routes);
        self::assertStringContainsString('$this->connections->configure($examination);', $middleware);
        self::assertStringContainsString('if ($examination === null)', $middleware);
        self::assertStringContainsString('$this->connections->disconnect();', $middleware);
        self::assertStringContainsString('NonCadreReadinessService::class', $navigation);
    }
    public function test_nc5_dashboard_status_is_available_when_current_nc4_is_finalized(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/NonCadre/NonCadreController.php'));
        $view = file_get_contents(resource_path('views/non-cadre/index.blade.php'));

        self::assertStringContainsString("->where('phase', 'FINALIZED')", $controller);
        self::assertStringContainsString("->where('is_stale', false)", $controller);
        self::assertStringContainsString("\$reportingStatus = \$nc4Final ? 'available' : 'not_started';", $controller);
        self::assertStringContainsString("'status' => \$reportingStatus", $controller);
        self::assertStringContainsString("['finalized', 'available']", $view);
        self::assertStringContainsString('NC4 allocation is finalized/current. Interactive reports and publishing exports are available.', $view);
    }

}
