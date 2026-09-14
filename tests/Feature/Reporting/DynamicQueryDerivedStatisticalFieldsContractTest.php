<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryDerivedStatisticalFieldsContractTest extends TestCase
{
    public function test_confirmed_derived_statistical_fields_are_semantic_and_queryable(): void
    {
        $fields = require base_path('config/dynamic-reports.php');

        $registry = $fields['fields'];

        $this->assertSame('number', $registry['candidate.age_years']['type']);
        $this->assertSame('age_years', $registry['candidate.age_years']['derived']);
        $this->assertContains('between', $registry['candidate.age_years']['operators']);

        $this->assertSame('enum', $registry['candidate.age_group']['type']);
        $this->assertSame('age_group', $registry['candidate.age_group']['derived']);
        $this->assertSame([
            'under_21' => 'Under 21',
            '21_23' => '21-23',
            '24_26' => '24-26',
            '27_29' => '27-29',
            '30_32' => '30-32',
            'above_32' => 'Above 32',
        ], $registry['candidate.age_group']['options']);

        $this->assertSame('number', $registry['candidate.quota_count']['type']);
        $this->assertStringContainsString('has_ff_quota = 2', $registry['candidate.quota_count']['expression']);
        $this->assertStringContainsString('has_em_quota = 1', $registry['candidate.quota_count']['expression']);
        $this->assertStringContainsString('has_phc_quota = 1', $registry['candidate.quota_count']['expression']);

        $this->assertSame('boolean', $registry['candidate.has_any_quota']['type']);
        $this->assertSame(['1' => 'Yes', '0' => 'No'], $registry['candidate.has_any_quota']['options']);

        $this->assertSame(['ALLOCATED' => 'Allocated', 'NOT_ALLOCATED' => 'Not Allocated'], $registry['allocation.outcome']['options']);
        $this->assertSame('allocation_disposition', $registry['allocation.publication_disposition']['source']);
        $this->assertSame('Withheld', $registry['allocation.publication_disposition']['options']['WITHHELD']);
        $this->assertSame('Cancelled', $registry['allocation.publication_disposition']['options']['CANCELLED']);

        // Cadre can be queried either by master-interpreted lookup (e.g. DENT)
        // or by the raw business code (e.g. 270), then combined with A5.5 disposition.
        $this->assertSame('cadre_effective', $registry['allocation.cadre']['lookup']);
        $this->assertContains('eq', $registry['allocation.cadre']['operators']);
        $this->assertSame('number', $registry['allocation.cadre_code']['type']);
        $this->assertContains('eq', $registry['allocation.cadre_code']['operators']);
        $this->assertContains('eq', $registry['allocation.publication_disposition']['operators']);
        $this->assertSame('WITHHELD', array_search('Withheld', $registry['allocation.publication_disposition']['options'], true));
    }

    public function test_age_fields_use_examination_age_calculation_date_and_disposition_is_queue_bound(): void
    {
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));
        $authority = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicReportAuthority.php'));
        $xlsx = file_get_contents(app_path('Jobs/ProcessDynamicQueryXlsxExport.php'));
        $pdf = file_get_contents(app_path('Jobs/ProcessDynamicQueryPdfExport.php'));

        $this->assertStringContainsString('age_calculation_date', $compiler);
        $this->assertStringContainsString('TIMESTAMPDIFF(YEAR, registrations.birth_date', $compiler);
        $this->assertStringContainsString('groupByRaw((string) $meta[\'expression\'])', $compiler);
        $this->assertStringContainsString('orderByRaw((string) ($metadata[$id][\'expression\']', $compiler);
        $this->assertStringContainsString("in_array('allocation_disposition', \$sources, true)", $compiler);
        $this->assertStringContainsString("leftJoin('allocation_result_dispositions'", $compiler);

        $this->assertStringContainsString("'allocation_disposition_revision' => null", $authority);
        $this->assertStringContainsString("'allocation_disposition_hash' => null", $authority);
        $this->assertStringContainsString('AllocationResultDispositionState::query()', $authority);

        foreach ([$xlsx, $pdf] as $job) {
            $this->assertStringContainsString('allocation_disposition_revision', $job);
            $this->assertStringContainsString('allocation_disposition_hash', $job);
        }
    }
}
