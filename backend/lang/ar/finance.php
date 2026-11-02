<?php

declare(strict_types=1);

return [
    'amount' => ':amount :currency',
    'currencies' => [
        'SAR' => 'ر.س',
    ],
    'purposes' => [
        'accredited' => 'التقييم المعتمد',
        'bag' => 'حقيبة التميز السريع',
    ],
    'statuses' => [
        'created' => 'منشأة',
        'pending' => 'قيد المعالجة',
        'paid' => 'مدفوعة',
        'failed' => 'فاشلة',
        'cancelled' => 'ملغاة',
        'refunded' => 'مستردة',
    ],
    'methods' => [
        'creditcard' => 'بطاقة ائتمانية',
        'mada' => 'مدى',
        'applepay' => 'Apple Pay',
        'stcpay' => 'STC Pay',
    ],
    'receipt' => [
        'title' => 'إيصال دفع',
        'number' => 'رقم الإيصال',
        'issued_on' => 'تاريخ الإصدار',
        'payment_reference' => 'مرجع الدفع',
        'entity' => 'الجهة',
        'service' => 'الخدمة',
        'price_before_vat' => 'السعر قبل ضريبة القيمة المضافة',
        'vat' => 'ضريبة القيمة المضافة (:rate%)',
        'total' => 'الإجمالي',
        'method' => 'وسيلة الدفع',
        'paid_at' => 'تاريخ الدفع',
        'test_notice' => 'عملية دفع تجريبية. لا يحمل هذا الإيصال رقمًا رسميًا ولا قيمة مالية له.',
        'void_notice' => 'أُلغي هذا الإيصال بعد تسجيل مبلغ مسترد.',
        'footer' => 'هذا المستند إيصال دفع صادر عن منصة مؤشر.',
    ],
    'export' => [
        'columns' => [
            'reference' => 'مرجع الدفع',
            'receipt_number' => 'رقم الإيصال',
            'created_at' => 'تاريخ الإنشاء',
            'paid_at' => 'تاريخ الدفع',
            'purpose' => 'الخدمة',
            'entity_reference' => 'رقم الجهة',
            'entity_name' => 'الجهة',
            'entity_email' => 'البريد الإلكتروني للجهة',
            'method' => 'وسيلة الدفع',
            'status' => 'الحالة',
            'price_before_vat' => 'السعر قبل ضريبة القيمة المضافة',
            'vat_rate' => 'نسبة ضريبة القيمة المضافة',
            'vat_amount' => 'ضريبة القيمة المضافة',
            'total' => 'الإجمالي',
            'currency' => 'العملة',
            'refunded_at' => 'تاريخ الاسترداد',
            'refund_amount' => 'المبلغ المسترد',
        ],
    ],
];
