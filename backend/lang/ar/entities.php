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
        'pending_review' => 'بانتظار المراجعة',
        'active' => 'نشطة',
        'deactivated' => 'موقوفة',
        'rejected' => 'مرفوضة',
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
        'official_email' => 'البريد الإلكتروني الرسمي',
        'website_url' => 'الموقع الإلكتروني',
        'authorized_person_name' => 'الشخص المفوّض بإدارة الحساب',
        'phone' => 'رقم الجوال',
        'marketing_consent' => 'أخبار المنصة وعروضها',
        'address_building_number' => 'رقم المبنى',
        'address_street' => 'الشارع',
        'address_district' => 'الحي',
        'address_city' => 'المدينة (العنوان الوطني)',
        'address_postal_code' => 'الرمز البريدي',
        'address_additional_number' => 'الرقم الإضافي',
        'address_short_code' => 'العنوان المختصر',
        'vat_registered' => 'التسجيل في ضريبة القيمة المضافة',
        'vat_number' => 'الرقم الضريبي',
        'vat_certificate' => 'شهادة التسجيل الضريبي',
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
