<?php

return [
    'preview_sizes' => [5, 10, 20],
    'default_preview_size' => 10,
    'max_selected_fields' => 30,
    'max_conditions' => 20,
    'max_condition_depth' => 4,
    'max_sorts' => 5,
    'max_groups' => 5,
    'max_saved_reports' => 100,

    /*
    | Semantic reporting contract. Internal source/expression metadata never
    | leaves the server; browser payloads receive only safe business metadata.
    */
    'fields' => [
        'candidate.reg' => [
            'label' => 'Registration Number', 'module' => 'Registration', 'type' => 'string',
            'source' => 'registrations', 'expression' => 'registrations.reg',
            'operators' => ['eq', 'neq', 'contains', 'starts_with', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'count_distinct'],
        ],
        'candidate.name' => [
            'label' => 'Candidate Name', 'module' => 'Registration', 'type' => 'string',
            'source' => 'registrations', 'expression' => 'registrations.name',
            'operators' => ['eq', 'neq', 'contains', 'starts_with', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'count_distinct'],
        ],
        'candidate.father_name' => [
            'label' => 'Father Name', 'module' => 'Registration', 'type' => 'string',
            'source' => 'registrations', 'expression' => 'registrations.father_name',
            'operators' => ['eq', 'neq', 'contains', 'starts_with', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count', 'count_distinct'],
        ],
        'candidate.birth_date' => [
            'label' => 'Date of Birth', 'module' => 'Registration', 'type' => 'date',
            'source' => 'registrations', 'expression' => 'registrations.birth_date',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'min', 'max'],
        ],
        'candidate.division_code' => [
            'label' => 'Division Code', 'module' => 'Registration', 'type' => 'number',
            'source' => 'registrations', 'expression' => 'registrations.division_code',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'count_distinct'],
        ],
        'candidate.bachelor_subject_code' => [
            'label' => 'Bachelor Subject Code', 'module' => 'Registration', 'type' => 'number',
            'source' => 'registrations', 'expression' => 'registrations.bachelor_subject_code',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'count_distinct'],
        ],
        'candidate.sex_code' => [
            'label' => 'Sex', 'module' => 'Registration', 'type' => 'enum',
            'source' => 'registrations', 'expression' => 'registrations.sex_code',
            'operators' => ['eq', 'neq', 'in'], 'options' => ['1' => 'Male', '2' => 'Female', '3' => 'Third Gender'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'candidate.cadre_category' => [
            'label' => 'Cadre Category', 'module' => 'Registration', 'type' => 'enum',
            'source' => 'registrations', 'expression' => 'registrations.cadre_category',
            'operators' => ['eq', 'neq', 'in'], 'options' => ['1' => 'GG', '2' => 'TT', '3' => 'GT'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'candidate.district_code' => [
            'label' => 'District Code', 'module' => 'Registration', 'type' => 'string',
            'source' => 'registrations', 'expression' => 'registrations.district_code',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'count_distinct'],
        ],
        'candidate.has_quota' => [
            'label' => 'Has Quota', 'module' => 'Registration', 'type' => 'boolean',
            'source' => 'registrations', 'expression' => 'registrations.has_quota',
            'operators' => ['eq'], 'options' => ['1' => 'Yes', '0' => 'No'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],

        'preliminary.mark' => [
            'label' => 'Preliminary Mark', 'module' => 'Preliminary', 'type' => 'number',
            'source' => 'preliminary', 'expression' => 'preliminary_results.mark',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'min', 'max', 'avg', 'sum'],
        ],
        'preliminary.result_status' => [
            'label' => 'Preliminary Result', 'module' => 'Preliminary', 'type' => 'enum',
            'source' => 'preliminary', 'expression' => 'preliminary_results.result_status',
            'operators' => ['eq', 'neq', 'in'], 'options' => ['pass' => 'Pass', 'fail' => 'Fail', 'cancelled' => 'Cancelled'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'preliminary.candidate_status' => [
            'label' => 'Preliminary Candidate Status', 'module' => 'Preliminary', 'type' => 'enum',
            'source' => 'preliminary', 'expression' => 'preliminary_results.candidate_status',
            'operators' => ['eq', 'neq', 'in'], 'options' => ['active' => 'Active', 'withheld' => 'Withheld', 'cancelled' => 'Cancelled', 'expelled' => 'Expelled'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'preliminary.applied_cutoff' => [
            'label' => 'Preliminary Applied Cut-off', 'module' => 'Preliminary', 'type' => 'number',
            'source' => 'preliminary', 'expression' => 'preliminary_results.applied_cutoff_mark',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count', 'min', 'max', 'avg'],
        ],
        'written.status' => [
            'label' => 'Written Candidate Status', 'module' => 'Written', 'type' => 'enum',
            'source' => 'written', 'expression' => 'written_results.status',
            'operators' => ['eq', 'neq', 'in'], 'options' => ['active' => 'Active', 'withheld' => 'Withheld', 'cancelled' => 'Cancelled', 'expelled' => 'Expelled'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'written.qualified_track' => [
            'label' => 'Written Qualified Track', 'module' => 'Written', 'type' => 'enum',
            'source' => 'written', 'expression' => 'written_results.written_qualified_track',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'], 'options' => ['GG'=>'GG','TT'=>'TT','GT'=>'GT','GN'=>'GN','T'=>'T'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'written.general_result' => [
            'label' => 'Written General Result', 'module' => 'Written', 'type' => 'enum',
            'source' => 'written', 'expression' => 'written_results.general_result_status',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'], 'options' => ['pass'=>'Pass','fail'=>'Fail','not_applicable'=>'Not Applicable'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'written.technical_result' => [
            'label' => 'Written Technical Result', 'module' => 'Written', 'type' => 'enum',
            'source' => 'written', 'expression' => 'written_results.technical_result_status',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'], 'options' => ['pass'=>'Pass','fail'=>'Fail','not_applicable'=>'Not Applicable'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'written.general_total' => [
            'label' => 'Written General Total', 'module' => 'Written', 'type' => 'number',
            'source' => 'written', 'expression' => 'written_results.general_counted_total',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count', 'min', 'max', 'avg', 'sum'],
        ],
        'written.technical_total' => [
            'label' => 'Written Technical Total', 'module' => 'Written', 'type' => 'number',
            'source' => 'written', 'expression' => 'written_results.technical_counted_total',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count', 'min', 'max', 'avg', 'sum'],
        ],
        'viva.mark' => [
            'label' => 'Viva Mark', 'module' => 'Viva', 'type' => 'number',
            'source' => 'viva', 'expression' => 'viva_results.mark',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count', 'min', 'max', 'avg', 'sum'],
        ],
        'viva.result_status' => [
            'label' => 'Viva Result', 'module' => 'Viva', 'type' => 'enum',
            'source' => 'viva', 'expression' => 'viva_results.viva_result_status',
            'operators' => ['eq', 'neq', 'in'], 'options' => ['pass'=>'Pass','fail'=>'Fail','pending'=>'Pending','not_applicable'=>'Not Applicable'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'viva.attendance_status' => [
            'label' => 'Viva Attendance', 'module' => 'Viva', 'type' => 'enum',
            'source' => 'viva', 'expression' => 'viva_results.attendance_status',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'], 'options' => ['appeared'=>'Appeared','absent'=>'Absent'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'viva.issue_flag' => [
            'label' => 'Viva Issue Flag', 'module' => 'Viva', 'type' => 'boolean',
            'source' => 'viva', 'expression' => 'viva_results.issue_flag',
            'operators' => ['eq'], 'options' => ['1'=>'Yes','0'=>'No'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'viva.invalid_flag' => [
            'label' => 'Viva Invalid Flag', 'module' => 'Viva', 'type' => 'boolean',
            'source' => 'viva', 'expression' => 'viva_results.invalid_flag',
            'operators' => ['eq'], 'options' => ['1'=>'Yes','0'=>'No'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'tabulation.preliminary_mark' => [
            'label' => 'Tabulation Preliminary Mark', 'module' => 'Tabulation', 'type' => 'number',
            'source' => 'tabulation', 'expression' => 'tabulation_results.preliminary_mark',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count','min','max','avg','sum'],
        ],
        'tabulation.general_written_total' => [
            'label' => 'Tabulation General Written Total', 'module' => 'Tabulation', 'type' => 'number',
            'source' => 'tabulation', 'expression' => 'tabulation_results.general_written_total',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count','min','max','avg','sum'],
        ],
        'tabulation.technical_written_total' => [
            'label' => 'Tabulation Technical Written Total', 'module' => 'Tabulation', 'type' => 'number',
            'source' => 'tabulation', 'expression' => 'tabulation_results.technical_written_total',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count','min','max','avg','sum'],
        ],
        'tabulation.viva_mark' => [
            'label' => 'Tabulation Viva Mark', 'module' => 'Tabulation', 'type' => 'number',
            'source' => 'tabulation', 'expression' => 'tabulation_results.viva_mark',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count','min','max','avg','sum'],
        ],
        'tabulation.general_grand_total' => [
            'label' => 'General Grand Total', 'module' => 'Tabulation', 'type' => 'number',
            'source' => 'tabulation', 'expression' => 'tabulation_results.general_grand_total',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count','min','max','avg','sum'],
        ],
        'tabulation.technical_grand_total' => [
            'label' => 'Technical Grand Total', 'module' => 'Tabulation', 'type' => 'number',
            'source' => 'tabulation', 'expression' => 'tabulation_results.technical_grand_total',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => false, 'aggregates' => ['count','min','max','avg','sum'],
        ],
        'tabulation.general_merit_eligible' => [
            'label' => 'General Merit Eligible', 'module' => 'Tabulation', 'type' => 'boolean',
            'source' => 'tabulation', 'expression' => 'tabulation_results.general_merit_eligible',
            'operators' => ['eq'], 'options' => ['1'=>'Yes','0'=>'No'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'tabulation.technical_merit_eligible' => [
            'label' => 'Technical Merit Eligible', 'module' => 'Tabulation', 'type' => 'boolean',
            'source' => 'tabulation', 'expression' => 'tabulation_results.technical_merit_eligible',
            'operators' => ['eq'], 'options' => ['1'=>'Yes','0'=>'No'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],

        'merit.common_position' => [
            'label' => 'Common Merit Position', 'module' => 'Merit', 'type' => 'number',
            'source' => 'merit', 'expression' => 'merit_results.common_merit_position',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'min', 'max', 'avg'],
        ],
        'merit.general_position' => [
            'label' => 'General Merit Position', 'module' => 'Merit', 'type' => 'number',
            'source' => 'merit', 'expression' => 'merit_results.general_merit_position',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'min', 'max', 'avg'],
        ],
        'merit.technical_position' => [
            'label' => 'Technical Merit Position', 'module' => 'Merit', 'type' => 'number',
            'source' => 'merit', 'expression' => 'merit_results.technical_merit_position',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'min', 'max', 'avg'],
        ],
        'merit.written_track' => [
            'label' => 'Written Qualified Track', 'module' => 'Merit', 'type' => 'enum',
            'source' => 'merit', 'expression' => 'merit_results.written_qualified_track',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'merit.graduation_year' => [
            'label' => 'Graduation Year', 'module' => 'Merit', 'type' => 'number',
            'source' => 'merit', 'expression' => 'merit_results.graduation_year',
            'operators' => ['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'min', 'max', 'avg'],
        ],
        'allocation.cadre' => [
            'label' => 'Allocated Cadre', 'module' => 'Allocation', 'type' => 'entity',
            'source' => 'allocation', 'expression' => 'allocation_a5_candidate_results.cadre_code',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'], 'formatter' => 'cadre',
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'count_distinct'],
        ],
        'allocation.cadre_code' => [
            'label' => 'Allocated Cadre Code', 'module' => 'Allocation', 'type' => 'number',
            'source' => 'allocation', 'expression' => 'allocation_a5_candidate_results.cadre_code',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count', 'count_distinct'],
        ],
        'allocation.cadre_type' => [
            'label' => 'Allocated Cadre Type', 'module' => 'Allocation', 'type' => 'enum',
            'source' => 'allocation', 'expression' => 'allocation_a5_candidate_results.cadre_type',
            'operators' => ['eq', 'neq', 'in', 'is_blank', 'is_not_blank'], 'options' => ['GG' => 'General', 'TT' => 'Technical'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'allocation.basis' => [
            'label' => 'Allocation Basis', 'module' => 'Allocation', 'type' => 'enum',
            'source' => 'allocation', 'expression' => 'allocation_a5_candidate_results.allocation_basis',
            'operators' => ['eq', 'neq', 'in'], 'options' => ['MQ' => 'MQ', 'CFF' => 'CFF', 'EM' => 'EM', 'PHC' => 'PHC'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
        'allocation.status' => [
            'label' => 'Allocation Validity', 'module' => 'Allocation', 'type' => 'enum',
            'source' => 'allocation', 'expression' => 'allocation_a5_candidate_results.overall_status',
            'operators' => ['eq', 'neq', 'in'],
            'selectable' => true, 'sortable' => true, 'groupable' => true, 'aggregates' => ['count'],
        ],
    ],
];
