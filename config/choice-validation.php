<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Allowed Choices
    |--------------------------------------------------------------------------
    |
    | The authoritative source-template and raw-choice limit for the current
    | deployment. Excel columns are generated dynamically as opt_01..opt_N.
    | Default is 20. Changing this value changes template/header validation
    | without requiring a database migration.
    |
    */
    'maximum_allowed_choices' => 20,
    'queue' => env('CHOICE_VALIDATION_QUEUE', 'imports'),

    // Import tuning. Keep these environment-driven like Preliminary/Written/Viva.
    'staging_chunk_size' => (int) env('CHOICE_STAGING_CHUNK_SIZE', 1000),
    'validation_chunk_size' => (int) env('CHOICE_VALIDATION_CHUNK_SIZE', 1000),
    'approval_chunk_size' => (int) env('CHOICE_APPROVAL_CHUNK_SIZE', 1000),
    'processing_chunk_size' => (int) env('CHOICE_PROCESSING_CHUNK_SIZE', 500),
    'detail_insert_chunk_size' => (int) env('CHOICE_DETAIL_INSERT_CHUNK_SIZE', 1000),
];
