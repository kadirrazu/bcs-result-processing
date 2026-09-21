<?php
namespace Tests\Feature\NonCadre;
use Tests\TestCase;
final class NonCadreReportingFoundationContractTest extends TestCase
{
    public function test_nc5_is_independent_and_exposes_required_reporting_surfaces(): void
    {
        $routes=file_get_contents(base_path('routes/non-cadre.php'));
        $this->assertStringContainsString("prefix('reporting')",$routes);
        $this->assertStringContainsString("exports/txt",$routes);
        $this->assertStringContainsString("/docx",$routes);
        $this->assertStringContainsString("/{mode}/common-merit",$routes);
        $this->assertStringContainsString("/{mode}/posts/{postCode}",$routes);
        $service=file_get_contents(app_path('Services/NonCadre/Reporting/NonCadreReportingService.php'));
        $this->assertStringContainsString("where('status', 'finalized')",$service);
        $this->assertStringContainsString("where('is_stale', false)",$service);
        $this->assertStringContainsString('common_merit_position',$service);
        $this->assertStringContainsString('allocation_ready_choices',$service);
        $this->assertStringContainsString('allocated_post',$service);
        $postsView=file_get_contents(resource_path('views/non-cadre/reporting/posts.blade.php'));
        $this->assertStringContainsString('Allocated Post',$postsView);
        $this->assertStringContainsString('text-success',$postsView);
        $this->assertStringContainsString('text-primary',$postsView);
        $this->assertStringContainsString('text-danger',$postsView);
        $job=file_get_contents(app_path('Jobs/ProcessNonCadreReportingExport.php'));
        $this->assertStringContainsString("onQueue('imports')",$job);
        $this->assertStringNotContainsString('AllocationA6ExportService',$job);
    }

    public function test_no_cadre_reporting_file_is_part_of_nc5_implementation(): void
    {
        $controller=file_get_contents(app_path('Http/Controllers/NonCadre/Reporting/NonCadreReportingController.php'));
        $this->assertStringNotContainsString('CadreSectionReportingController',$controller);
        $this->assertStringNotContainsString('AllocationVerificationReportService',$controller);
    }
}
