<?php

return [
    'queue' => env('VIVA_IMPORT_QUEUE', 'imports'),

    'mapping_staging_chunk_size' => (int) env('VIVA_MAPPING_STAGING_CHUNK_SIZE', 4000),
    'mapping_validation_chunk_size' => (int) env('VIVA_MAPPING_VALIDATION_CHUNK_SIZE', 3000),
    'board_staging_chunk_size' => (int) env('VIVA_BOARD_STAGING_CHUNK_SIZE', 1500),
    'board_validation_chunk_size' => (int) env('VIVA_BOARD_VALIDATION_CHUNK_SIZE', 3000),

    'full_mark' => (float) env('VIVA_FULL_MARK', 100),
    'pass_percent' => (float) env('VIVA_PASS_PERCENT', 50),
    'high_mark_review_percent' => (float) env('VIVA_HIGH_MARK_REVIEW_PERCENT', 80),

    'mapping_headers' => [
        'user',
        'reg',
        'code',
    ],

    'board_headers' => [
        'viva_date',
        'member_id',
        'code',
        'mark',
        'viva_cff',
        'viva_em',
        'viva_phc',
        'invalid',
        'issue',
    ],

    'board_required_headers' => [
        'viva_date',
        'member_id',
        'code',
        'mark',
    ],

    'operational_statuses' => [
        'active',
        'cancelled',
        'withheld',
        'expelled',
    ],
];
