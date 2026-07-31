<?php

return [
    'chunk_size' => (int) env('REGISTRATION_IMPORT_CHUNK_SIZE', 1000),
    'queue' => env('REGISTRATION_IMPORT_QUEUE', 'imports'),
    'headers' => [
        'user', 'reg', 'name', 'fname', 'mname', 'b_date', 'sex', 'district',
        'university', 'b_subject', 'post_related_subject',
        'has_ff_quota', 'has_em_quota', 'has_phc_quota', 'name_bn', 'fname_bn',
        'mname_bn', 'national_id', 'cadre_category', 'status', 'comment',
    ],
];
