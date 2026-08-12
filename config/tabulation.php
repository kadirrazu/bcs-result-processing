<?php

return [
    'queue' => env('TABULATION_QUEUE', 'imports'),
    'processing_chunk_size' => (int) env('TABULATION_PROCESSING_CHUNK_SIZE', 1000),
    'grand_total_review_percent' => (float) env('TABULATION_GRAND_TOTAL_REVIEW_PERCENT', 75),
    'export_batch_size' => (int) env('TABULATION_EXPORT_BATCH_SIZE', 2000),
];
