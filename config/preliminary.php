<?php

return [
    'queue' => env('PRELIMINARY_IMPORT_QUEUE', 'imports'),
    'staging_chunk_size' => (int) env('PRELIMINARY_STAGING_CHUNK_SIZE', 4000),
    'validation_chunk_size' => (int) env('PRELIMINARY_VALIDATION_CHUNK_SIZE', 5000),
    'validation_write_chunk_size' => (int) env('PRELIMINARY_VALIDATION_WRITE_CHUNK_SIZE', 2000),
    'merge_chunk_size' => (int) env('PRELIMINARY_MERGE_CHUNK_SIZE', 3000),
    'headers' => ['user', 'reg', 'mark', 'candidate_status'],
];
