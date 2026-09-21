<?php
namespace Tests\Feature\NonCadre;
use Tests\TestCase;
final class NonCadreCandidateDataExportContractTest extends TestCase
{
    public function test_nc5_candidate_xlsx_and_dbf_exports_are_independent_and_complete(): void
    {
        $routes=file_get_contents(base_path('routes/non-cadre.php'));
        self::assertStringContainsString("exports/candidates/{scope}/{format}",$routes);
        $service=file_get_contents(app_path('Services/NonCadre/Reporting/NonCadreCandidateExportService.php'));
        foreach(['user','reg','name','cff','em','phc','allocation_ready_choice','common_merit_position','allocation_status','allocated_post_code','allocation_basis'] as $field) self::assertStringContainsString($field,$service);
        self::assertStringContainsString("['eligible', 'allocated']",$service);
        self::assertStringContainsString("['xlsx', 'dbf']",$service);
        self::assertStringContainsString("whereNotNull('a.post_code')",$service);
        self::assertStringContainsString('SpreadsheetReportWriter',$service);
        self::assertStringContainsString('DbfReportWriter',$service);
        $job=file_get_contents(app_path('Jobs/ProcessNonCadreReportingExport.php'));
        self::assertStringContainsString("['XLSX','DBF']",$job);
        self::assertStringContainsString('NonCadreCandidateExportService',$job);
    }
}
