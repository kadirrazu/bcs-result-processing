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
            ['label' => 'Written Marks', 'route' => 'written.index', 'patterns' => ['written.*']],
            ['label' => 'Viva Marks', 'route' => null, 'patterns' => ['viva-marks.*']],
            ['label' => 'Choice Lists', 'route' => null, 'patterns' => ['choices.*']],
            ['label' => 'Tabulation', 'route' => null, 'patterns' => ['tabulation.*']],
            ['label' => 'Merit', 'route' => null, 'patterns' => ['merit.*']],
            ['label' => 'Allocation', 'route' => null, 'patterns' => ['allocation.*']],
            ['label' => 'Reports', 'route' => null, 'patterns' => ['examination-reports.*']],
        ],
    ],
];
