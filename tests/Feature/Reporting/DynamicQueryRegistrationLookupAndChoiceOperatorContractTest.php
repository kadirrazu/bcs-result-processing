<?php

namespace Tests\Feature\Reporting;

use Tests\TestCase;

final class DynamicQueryRegistrationLookupAndChoiceOperatorContractTest extends TestCase
{
    public function test_registration_semantics_include_identity_quota_subject_and_master_interpretation_fields(): void
    {
        $config = file_get_contents(config_path('dynamic-reports.php'));

        foreach ([
            "'candidate.national_id'", "'candidate.mother_name'", "'candidate.graduation_year'",
            "'candidate.district_code'", "'candidate.district_name'",
            "'candidate.division_code'", "'candidate.division_name'",
            "'candidate.university_code'", "'candidate.university_name'",
            "'candidate.bachelor_subject_code'", "'candidate.bachelor_subject_name'",
            "'candidate.prs_code'", "'candidate.prs_name'",
            "'candidate.cff'", "'candidate.em'", "'candidate.phc'",
        ] as $field) {
            self::assertStringContainsString($field, $config);
        }

        self::assertStringNotContainsString("'preliminary.applied_cutoff'", $config);
        self::assertStringNotContainsString("'merit.graduation_year'", $config);
    }

    public function test_master_interpreted_fields_resolve_browser_options_without_cross_database_joins(): void
    {
        $lookup = file_get_contents(app_path('Services/Reporting/DynamicQuery/SemanticLookupRegistry.php'));
        $registry = file_get_contents(app_path('Services/Reporting/DynamicQuery/SemanticFieldRegistry.php'));
        $formatter = file_get_contents(app_path('Services/Reporting/DynamicQuery/SemanticValueFormatter.php'));

        foreach (['Gender::query()', 'District::query()', 'Division::query()', 'University::query()', 'BachelorSubject::query()', 'PostRelatedSubject::query()', 'CadreMaster::query()', 'CadreSubMaster::query()'] as $contract) {
            self::assertStringContainsString($contract, $lookup);
        }
        self::assertStringContainsString("isset(\$field['lookup'])", $registry);
        self::assertStringContainsString('$this->lookups->options', $registry);
        self::assertStringContainsString("isset(\$meta['lookup'])", $formatter);
        self::assertStringContainsString('$this->lookups->label', $formatter);
    }

    public function test_nid_supports_empty_and_partial_text_queries_and_choice_ui_explains_exact_matching(): void
    {
        $config = file_get_contents(config_path('dynamic-reports.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString("'label' => 'NID'", $config);
        self::assertStringContainsString("'contains', 'starts_with', 'ends_with', 'is_blank', 'is_not_blank'", $config);
        self::assertStringContainsString("'ends_with' =>", $compiler);
        self::assertStringContainsString('Contains choice code', $view);
        self::assertStringContainsString('Choice codes are matched as exact list items, never as substrings.', $view);
    }

    public function test_computed_boolean_quota_fields_use_raw_semantic_expressions_for_conditions(): void
    {
        $config = file_get_contents(config_path('dynamic-reports.php'));
        $compiler = file_get_contents(app_path('Services/Reporting/DynamicQuery/DynamicQueryCompiler.php'));
        $view = file_get_contents(resource_path('views/reporting/dynamic-query/index.blade.php'));

        self::assertStringContainsString("'(registrations.has_ff_quota = 2)'", $config);
        self::assertStringContainsString("'(registrations.has_em_quota = 1)'", $config);
        self::assertStringContainsString("'(registrations.has_phc_quota = 1)'", $config);
        self::assertStringContainsString('applyBooleanCondition', $compiler);
        self::assertStringContainsString("'orWhereRaw' : 'whereRaw'", $compiler);
        self::assertStringContainsString('Boolean semantic fields may be computed SQL expressions', $compiler);
        self::assertStringContainsString('readJsonResponse', $view);
        self::assertStringContainsString('Server returned a non-JSON response', $view);
    }
}
