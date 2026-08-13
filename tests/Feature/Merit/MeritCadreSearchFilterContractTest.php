<?php

namespace Tests\Feature\Merit;

use Tests\TestCase;

final class MeritCadreSearchFilterContractTest extends TestCase
{
    public function test_individual_cadre_listing_has_name_reg_user_search_before_listing(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/MeritController.php'));
        $view = file_get_contents(resource_path('views/merit/cadre.blade.php'));

        $this->assertStringContainsString("query('search', '')", $controller);
        $this->assertStringContainsString("where('m.reg', 'like'", $controller);
        $this->assertStringContainsString("orWhere('m.user_id', 'like'", $controller);
        $this->assertStringContainsString("orWhere('r.name', 'like'", $controller);
        $this->assertStringContainsString('Search by Name, REG or USER', $view);
        $this->assertLessThan(strpos($view, '<table class="table table-vcenter">'), strpos($view, '<form class="card card-body mb-3"'));
    }
}
