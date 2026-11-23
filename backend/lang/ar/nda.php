<?php

declare(strict_types=1);

return [
    'super_admin' => 'مدير النظام الرئيسي',
    'statuses' => [
        'accepted' => 'وافق',
        'pending' => 'بانتظار الموافقة',
        'out_of_scope' => 'غير مشمول',
    ],
    'attributes' => [
        'change_summary' => 'ملخص التغييرات',
        'change_summary_ar' => 'ملخص التغييرات بالعربية',
        'change_summary_en' => 'ملخص التغييرات بالإنجليزية',
        'reason' => 'السبب',
        'confirm_irreversible' => 'تأكيد النشر',
        'roles' => 'الأدوار المشمولة',
        'reminder_days' => 'مدة التذكير',
    ],
    'export' => [
        'role_separator' => '، ',
        'columns' => [
            'name' => 'الاسم',
            'email' => 'البريد الإلكتروني',
            'roles' => 'الأدوار الحالية',
            'version' => 'النسخة السارية',
            'status' => 'الحالة',
            'accepted_at' => 'تاريخ الموافقة',
            'accepted_roles' => 'الأدوار عند الموافقة',
            'ip_address' => 'عنوان IP',
        ],
    ],
];
