<?php

return [
    'queue' => env('WRITTEN_IMPORT_QUEUE', 'imports'),

    /*
    |--------------------------------------------------------------------------
    | Evaluation percentages
    |--------------------------------------------------------------------------
    |
    | These are percentages, never fixed pass marks. Services must derive the
    | effective threshold from the configured full mark.
    */
    'written_pass_percent' => (float) env('WRITTEN_PASS_PERCENT', 50),
    'paper_crash_percent' => (float) env('WRITTEN_PAPER_CRASH_PERCENT', 30),
    'high_mark_review_percent' => (float) env('WRITTEN_HIGH_MARK_REVIEW_PERCENT', 75),

    /*
    |--------------------------------------------------------------------------
    | Authoritative written subject metadata
    |--------------------------------------------------------------------------
    |
    | PRS is the generic processing identity for a candidate-specific
    | post-related subject. The actual subject code is stored as prs_code.
    */
    'subjects' => [
        '001' => ['full_mark' => 100, 'display_order' => 1],
        '002' => ['full_mark' => 200, 'display_order' => 2],
        '003' => ['full_mark' => 200, 'display_order' => 3],
        '005' => ['full_mark' => 200, 'display_order' => 4],
        '007' => ['full_mark' => 100, 'display_order' => 5],
        '008' => ['full_mark' => 50, 'display_order' => 6],
        '009' => ['full_mark' => 50, 'display_order' => 7],
        '010' => ['full_mark' => 100, 'display_order' => 8],
        'PRS' => ['full_mark' => 200, 'display_order' => 9],
    ],

    'tracks' => [
        'general' => [
            'subjects' => ['002', '003', '005', '007', '008', '009', '010'],
        ],
        'technical' => [
            'subjects' => ['001', '003', '005', '007', '008', '009', 'PRS'],
        ],
    ],

    'combined_groups' => [
        '008_009' => [
            'subjects' => ['008', '009'],
        ],
    ],

    'prs_subject_code' => 'PRS',

    /* Exact Excel template contract. data_source_note never drives status. */
    'headers' => [
        'user',
        'reg',
        's001_mark',
        's002_mark',
        's003_mark',
        's005_mark',
        's007_mark',
        's008_mark',
        's009_mark',
        's010_mark',
        'prs_code',
        'prs_mark',
        'data_source_note',
    ],
];
