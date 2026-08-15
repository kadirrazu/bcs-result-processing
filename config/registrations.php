<?php

return [
    'queue' => env('REGISTRATION_IMPORT_QUEUE', 'imports'),

    // MySQL prepared statements are limited to 65,535 placeholders.
    // Keep a safety margin because Registration staging/merge rows contain many columns.
    'bulk_placeholder_budget' => (int) env('REGISTRATION_BULK_PLACEHOLDER_BUDGET', 60000),
    'staging_chunk_size' => (int) env('REGISTRATION_STAGING_CHUNK_SIZE', 1500),
    'large_import_threshold' => (int) env('REGISTRATION_LARGE_IMPORT_THRESHOLD', 100000),
    'large_staging_chunk_size' => (int) env('REGISTRATION_LARGE_STAGING_CHUNK_SIZE', 500),
    'staging_throttle_ms' => (int) env('REGISTRATION_STAGING_THROTTLE_MS', 15),
    'validation_chunk_size' => (int) env('REGISTRATION_VALIDATION_CHUNK_SIZE', 5000),
    'validation_write_chunk_size' => (int) env('REGISTRATION_VALIDATION_WRITE_CHUNK_SIZE', 2000),
    'merge_chunk_size' => (int) env('REGISTRATION_MERGE_CHUNK_SIZE', 1500),

    // Required source headers preserve the existing Registration import contract.
    'required_headers' => [
        'user', 'reg', 'name', 'fname', 'mname', 'b_date', 'sex', 'district',
        'university', 'b_subject', 'post_related_subject',
        'has_ff_quota', 'has_em_quota', 'has_phc_quota', 'name_bn', 'fname_bn',
        'mname_bn', 'national_id', 'cadre_category', 'status', 'comment',
    ],

    // Optional identity-supporting fields used later for previous-BCS candidate matching.
    'optional_headers' => [
        'ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'graduation_year',
    ],

    // Official template order. Optional columns may be omitted from uploaded source files.
    'headers' => [
        'user', 'reg', 'name', 'fname', 'mname', 'b_date',
        'ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'graduation_year',
        'sex', 'district', 'university', 'b_subject', 'post_related_subject',
        'has_ff_quota', 'has_em_quota', 'has_phc_quota', 'name_bn', 'fname_bn',
        'mname_bn', 'national_id', 'cadre_category', 'status', 'comment',
    ],
];
