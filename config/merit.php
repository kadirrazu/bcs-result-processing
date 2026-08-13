<?php
return [
    'queue' => env('MERIT_QUEUE', 'imports'),
    'insert_chunk_size' => (int) env('MERIT_INSERT_CHUNK_SIZE', 1000),
    'page_size' => (int) env('MERIT_PAGE_SIZE', 100),
];
