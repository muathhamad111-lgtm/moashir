<?php

declare(strict_types=1);

return [

    'statuses' => [
        'invited' => 'مدعو',
        'active' => 'نشط',
        'locked' => 'مقفل',
        'suspended' => 'موقوف',
        'expired' => 'انتهت صلاحية الدعوة',
    ],

    'export' => [
        'role_separator' => '، ',
        'columns' => [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'roles' => 'الأدوار',
            'status' => 'الحالة',
            'last_login_at' => 'آخر تسجيل دخول',
            'created_at' => 'تاريخ الإنشاء',
        ],
    ],

];
