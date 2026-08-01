<?php

return [
    'queue' => env('REGISTRATION_IMPORT_QUEUE', 'imports'),

    // Keep this below MySQL's prepared-statement placeholder limit.
    'staging_chunk_size' => (int) env('REGISTRATION_STAGING_CHUNK_SIZE', 2000),
    'validation_chunk_size' => (int) env('REGISTRATION_VALIDATION_CHUNK_SIZE', 5000),
    'validation_write_chunk_size' => (int) env('REGISTRATION_VALIDATION_WRITE_CHUNK_SIZE', 2000),
    'merge_chunk_size' => (int) env('REGISTRATION_MERGE_CHUNK_SIZE', 2000),

    'headers' => [
        'user', 'reg', 'name', 'fname', 'mname', 'b_date', 'sex', 'district',
        'university', 'b_subject', 'post_related_subject',
        'has_ff_quota', 'has_em_quota', 'has_phc_quota', 'name_bn', 'fname_bn',
        'mname_bn', 'national_id', 'cadre_category', 'status', 'comment',
    ],
];
