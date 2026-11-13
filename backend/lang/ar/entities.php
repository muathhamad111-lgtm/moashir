<?php

declare(strict_types=1);

return [

    'types' => [
        'civil_association' => 'جمعية أهلية',
        'civil_foundation' => 'مؤسسة أهلية',
        'waqf' => 'وقف',
    ],

    'sectors' => [
        'educational' => 'تعليمي',
        'social' => 'اجتماعي',
        'health' => 'صحي',
        'developmental' => 'تنموي',
    ],

    'sizes' => [
        'small' => 'صغيرة',
        'medium' => 'متوسطة',
        'large' => 'كبيرة',
    ],

    'statuses' => [
        'pending_verification' => 'بانتظار التحقق',
        'active' => 'نشطة',
        'deactivated' => 'موقوفة',
    ],

    'profile_fields' => [
        'name' => 'اسم الجهة',
        'entity_type' => 'نوع الجهة',
        'development_sector' => 'القطاع التنموي',
        'org_size' => 'حجم الجهة',
        'founding_year' => 'سنة التأسيس',
        'region_id' => 'المنطقة',
        'city_id' => 'المدينة',
        'governance_grade' => 'درجة الحوكمة',
        'employees_count' => 'عدد الموظفين',
        'annual_beneficiaries_count' => 'عدد المستفيدين سنويًا',
        'has_strategic_plan' => 'الخطة الاستراتيجية',
        'license_number' => 'رقم الترخيص',
        'unified_number' => 'الرقم الوطني الموحد',
        'founded_on' => 'تاريخ التأسيس',
        'website_url' => 'الموقع الإلكتروني',
        'authorized_person_name' => 'الشخص المفوّض بإدارة الحساب',
        'phone' => 'رقم الجوال',
        'marketing_consent' => 'أخبار المنصة وعروضها',
    ],

    'export' => [
        'yes' => 'نعم',
        'no' => 'لا',
        'columns' => [
            'reference' => 'الرقم المرجعي',
            'name_ar' => 'الاسم بالعربية',
            'name_en' => 'الاسم بالإنجليزية',
            'entity_type' => 'نوع الجهة',
            'development_sector' => 'القطاع التنموي',
            'org_size' => 'حجم الجهة',
            'founding_year' => 'سنة التأسيس',
            'region' => 'المنطقة',
            'city' => 'المدينة',
            'governance_grade' => 'درجة الحوكمة',
            'employees_count' => 'عدد الموظفين',
            'annual_beneficiaries_count' => 'عدد المستفيدين سنويًا',
            'has_strategic_plan' => 'الخطة الاستراتيجية',
            'status' => 'الحالة',
            'authorized_person_name' => 'الشخص المفوّض',
            'official_email' => 'البريد الإلكتروني الرسمي',
            'registered_at' => 'تاريخ التسجيل',
        ],
    ],

];
