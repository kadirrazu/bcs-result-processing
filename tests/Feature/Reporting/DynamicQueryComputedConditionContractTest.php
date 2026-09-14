<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryComputedConditionContractTest extends TestCase
{
    public function test_computed_semantic_fields_are_not_sent_to_builder_where_as_identifiers(): void
    {
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));
        $config = require base_path('config/dynamic-reports.php');
        $fields = $config['fields'];

        $this->assertStringContainsString('private function isComputedExpression', $compiler);
        $this->assertStringContainsString('private function applyComputedExpressionCondition', $compiler);
        $this->assertStringContainsString('private function computedInCondition', $compiler);

        $this->assertStringContainsString('CASE WHEN allocation_a5_candidate_results.registration_id IS NULL', $fields['allocation.publication_disposition']['expression']);
        $this->assertStringContainsString('CASE WHEN allocation_a5_candidate_results.registration_id IS NULL', $fields['allocation.outcome']['expression']);
        $this->assertSame('age_group', $fields['candidate.age_group']['derived']);
        $this->assertStringContainsString('has_ff_quota = 2', $fields['candidate.quota_count']['expression']);
    }

    public function test_active_disposition_can_be_combined_with_age_group_and_registration_count(): void
    {
        $config = require base_path('config/dynamic-reports.php');
        $fields = $config['fields'];

        $this->assertContains('eq', $fields['allocation.publication_disposition']['operators']);
        $this->assertSame('Active', $fields['allocation.publication_disposition']['options']['ACTIVE']);
        $this->assertTrue($fields['candidate.age_group']['groupable']);
        $this->assertContains('count', $fields['candidate.reg']['aggregates']);
    }
}
