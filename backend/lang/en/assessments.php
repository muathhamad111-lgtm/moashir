<?php

declare(strict_types=1);

return [

    'paths' => [
        'self' => 'Self assessment',
        'accredited' => 'Accredited assessment',
        'advanced' => 'Advanced assessment (Award)',
        'bag' => 'Quick Excellence Kit',
    ],

    'path_descriptions' => [
        'self' => 'An optional assessment your entity runs itself to gauge its readiness; it grants no level.',
        'accredited' => 'An assessment by an accredited assessor that grants the excellence level and its certificate. The request period is set automatically from its submission date.',
        'advanced' => 'An annual award for excellent entities; results are announced once the assessment is complete.',
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
