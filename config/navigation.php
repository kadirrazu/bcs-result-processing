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
            ['label' => 'Choice Lists', 'route' => null, 'patterns' => ['choices.*']],
            ['label' => 'Tabulation', 'route' => null, 'patterns' => ['tabulation.*']],
            ['label' => 'Merit', 'route' => null, 'patterns' => ['merit.*']],
            ['label' => 'Allocation', 'route' => null, 'patterns' => ['allocation.*']],
            ['label' => 'Reports', 'route' => null, 'patterns' => ['examination-reports.*']],
        ],
    ],
];
