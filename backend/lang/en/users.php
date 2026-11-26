<?php

declare(strict_types=1);

return [

    'statuses' => [
        'invited' => 'Invited',
        'active' => 'Active',
        'locked' => 'Locked',
        'suspended' => 'Suspended',
        'expired' => 'Invitation expired',
    ],

    'export' => [
        'role_separator' => ', ',
        'columns' => [
            'name' => 'Name',
            'email' => 'E-mail',
            'roles' => 'Roles',
            'status' => 'Status',
            'last_login_at' => 'Last sign-in',
            'created_at' => 'Created at',
        ],
    ],

];
