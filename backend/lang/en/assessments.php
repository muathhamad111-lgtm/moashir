<?php

declare(strict_types=1);

return [

    'paths' => [
        'self' => 'Self assessment',
        'accredited' => 'Accredited assessment',
        'advanced' => 'Advanced assessment (Award)',
        'bag' => 'Quick Excellence Kit',
    ],

    'qualifications' => [
        'accredited' => 'Accredited assessment',
        'advanced_initial' => 'Advanced assessment (Award) — initial assessment',
    ],

    'periods' => [
        'labels' => [
            'quarterly' => [
                '1' => 'Q1 :year',
                '2' => 'Q2 :year',
                '3' => 'Q3 :year',
                '4' => 'Q4 :year',
            ],
            'semi_annual' => [
                '1' => 'H1 :year',
                '2' => 'H2 :year',
            ],
            'annual' => [
                '1' => ':year',
            ],
        ],
        'attribution' => [
            'pre_periodic' => 'Before periodic assessment',
            'pending' => 'Set on submission',
        ],
    ],

];
