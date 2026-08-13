<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritCadreAllMeritTechDisplayContractTest extends TestCase
{
    public function test_all_merit_tech_uses_compact_json_format_everywhere(): void
    {
        $model = file_get_contents(app_path('Models/MeritResult.php'));
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $results = file_get_contents(resource_path('views/merit/results.blade.php'));
        $cadre = file_get_contents(resource_path('views/merit/cadre.blade.php'));
        $show = file_get_contents(resource_path('views/merit/show.blade.php'));

        $this->assertStringContainsString('function allMeritTechJson', $model);
        $this->assertStringContainsString('JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE', $model);
        $this->assertStringContainsString('MeritResult::allMeritTechJson($row->all_merit_tech)', $controller);
        $this->assertStringContainsString('MeritResult::allMeritTechJson($r->all_merit_tech)', $results);
        $this->assertStringContainsString('MeritResult::allMeritTechJson($r->all_merit_tech)', $cadre);
        $this->assertStringContainsString('MeritResult::allMeritTechJson($result->all_merit_tech)', $show);
        $this->assertStringNotContainsString('$techMap', $cadre);
    }
}
