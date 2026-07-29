<?php

return [
    'examination' => [
        'items' => [
            ['label' => 'Overview', 'route' => 'dashboard', 'patterns' => ['dashboard']],
            ['label' => 'Registrations', 'route' => null, 'patterns' => ['registrations.*']],
            ['label' => 'Choice Lists', 'route' => null, 'patterns' => ['choices.*']],
            ['label' => 'Written Marks', 'route' => null, 'patterns' => ['written-marks.*']],
            ['label' => 'Viva Marks', 'route' => null, 'patterns' => ['viva-marks.*']],
            ['label' => 'Tabulation', 'route' => null, 'patterns' => ['tabulation.*']],
            ['label' => 'Merit', 'route' => null, 'patterns' => ['merit.*']],
            ['label' => 'Allocation', 'route' => null, 'patterns' => ['allocation.*']],
            ['label' => 'Reports', 'route' => null, 'patterns' => ['examination-reports.*']],
        ],
    ],
];
