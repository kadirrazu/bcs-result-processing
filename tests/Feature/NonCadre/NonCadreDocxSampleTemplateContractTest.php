<?php

namespace Tests\Feature\NonCadre;

use Tests\TestCase;

final class NonCadreDocxSampleTemplateContractTest extends TestCase
{
    public function test_nc5_docx_page_and_route_expose_circular_ordered_sample_template(): void
    {
        $view = file_get_contents(resource_path('views/non-cadre/reporting/docx.blade.php'));
        $routes = file_get_contents(base_path('routes/non-cadre.php'));
        $controller = file_get_contents(app_path('Http/Controllers/NonCadre/Reporting/NonCadreReportingController.php'));
        $sample = file_get_contents(app_path('Services/NonCadre/Reporting/NonCadreDocxSampleTemplateService.php'));

        self::assertStringContainsString('Download Sample DOCX Template', $view);
        self::assertStringContainsString("name('docx.sample')", $routes);
        self::assertStringContainsString('downloadDocxSample', $controller);
        self::assertStringContainsString("->groupBy(fn (\$post) => (string) (\$post->post_grade ?? ''))", $sample);
        self::assertStringContainsString("->groupBy(fn (\$post) => (string) \$post->post_serial)", $sample);
        self::assertStringContainsString('post_sub_serial', $sample);
        self::assertStringContainsString('post_title_bn', $sample);
        self::assertStringContainsString('ministry_bn', $sample);
        self::assertStringContainsString('entity_bn', $sample);
        self::assertStringContainsString("'মন্ত্রণালয়ঃ '.\$ministry", $sample);
        self::assertStringContainsString("'সংস্থাঃ '.\$entity", $sample);
        self::assertStringContainsString("'পদের নামঃ '.\$postTitle", $sample);
        self::assertStringContainsString("'[[POST_'.\$this->key((string) \$post->post_code).']]'", $sample);
        self::assertStringContainsString("'[[TOTAL_'.\$this->key((string) \$post->post_code).']]'", $sample);
        self::assertStringContainsString("now()->format('Ymd-His')", $sample);
        self::assertStringContainsString("'Nikosh'", $sample);
        self::assertStringContainsString("'Times New Roman'", $sample);
    }

    public function test_nc5_sample_service_does_not_depend_on_cadre_reporting_services(): void
    {
        $sample = file_get_contents(app_path('Services/NonCadre/Reporting/NonCadreDocxSampleTemplateService.php'));
        self::assertStringNotContainsString('AllocationA6', $sample);
        self::assertStringNotContainsString('CadreSection', $sample);
    }
}
