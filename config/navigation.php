<?php

return [
    'examination' => [
        'items' => [
            ['label' => 'Overview', 'route' => 'dashboard', 'patterns' => ['dashboard']],
            ['label' => 'Registrations', 'route' => 'registrations.index', 'patterns' => ['registrations.*']],
            [
                'label' => 'Preliminary',
                'route' => 'preliminary.index',
                'patterns' => ['preliminary.*'],
            ],
            ['label' => 'Written', 'route' => 'written.index', 'patterns' => ['written.*']],
            ['label' => 'Viva', 'route' => 'viva.index', 'patterns' => ['viva.*']],
            ['label' => 'Circular', 'route' => 'circular.index', 'patterns' => ['circular.*']],
            ['label' => 'Choice Validation', 'route' => 'choice-validation.index', 'patterns' => ['choice-validation.*']],
            ['label' => 'Tabulation', 'route' => 'tabulation.index', 'patterns' => ['tabulation.*']],
            ['label' => 'Merit', 'route' => 'merit.index', 'patterns' => ['merit.*']],
            ['label' => 'Allocation', 'route' => null, 'patterns' => ['allocation.*']],
            ['label' => 'Reports', 'route' => null, 'patterns' => ['examination-reports.*']],
        ],
    ],
];
