<?php

return [
    'choice' => [
        'max_options' => (int) env('NON_CADRE_MAX_CHOICES', 20),
        'min_options' => 1,
    ],
    'quota' => [
        'mq_percent' => 93,
        'cff_percent' => 5,
        'em_percent' => 1,
        'phc_percent' => 1,
    ],
];
