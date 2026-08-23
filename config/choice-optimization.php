<?php

return [
    'default_enabled' => false,
    'previous_bcs_columns' => [
        'bcs_number', 'reg', 'name', 'fname', 'mname', 'b_date', 'district_name',
        'ssc_roll', 'ssc_year', 'hsc_roll', 'hsc_year', 'nid', 'cadre',
    ],
    'queue' => env('CHOICE_OPTIMIZATION_QUEUE', 'imports'),
    'import_chunk_size' => max(100, (int) env('CHOICE_OPTIMIZATION_IMPORT_CHUNK_SIZE', 1000)),
    'omr_choice_prefix' => 'opt_',
    'omr_max_choices' => (int) env('CHOICE_OPTIMIZATION_OMR_MAX_CHOICES', config('choice-validation.maximum_allowed_choices', 20)),
];
