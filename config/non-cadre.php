<?php
return [
    'choice' => [
        'max_options' => (int) env('NON_CADRE_MAX_CHOICES', 20),
        'min_options' => 1,
        'queue' => env('NON_CADRE_CHOICE_QUEUE', 'imports'),
    ],
    'allocation' => [
        'queue' => env('NON_CADRE_ALLOCATION_QUEUE', 'imports'),
    ],
];
