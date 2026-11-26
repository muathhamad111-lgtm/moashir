<?php

declare(strict_types=1);

return [
    'super_admin' => 'Super Admin',
    'statuses' => [
        'accepted' => 'Accepted',
        'pending' => 'Pending acceptance',
        'out_of_scope' => 'Not in scope',
    ],
    'attributes' => [
        'change_summary' => 'change summary',
        'change_summary_ar' => 'Arabic change summary',
        'change_summary_en' => 'English change summary',
        'reason' => 'reason',
        'confirm_irreversible' => 'publishing confirmation',
        'roles' => 'roles in scope',
        'reminder_days' => 'reminder delay',
    ],
    'export' => [
        'role_separator' => ', ',
        'columns' => [
            'name' => 'Name',
            'email' => 'Email',
            'roles' => 'Current roles',
            'version' => 'Current version',
            'status' => 'Status',
            'accepted_at' => 'Accepted at',
            'accepted_roles' => 'Roles at acceptance',
            'ip_address' => 'IP address',
        ],
    ],
];
