<?php

declare(strict_types=1);

return [
    'amount' => ':amount :currency',
    'currencies' => [
        'SAR' => 'SAR',
    ],
    'purposes' => [
        'accredited' => 'Accredited assessment',
        'bag' => 'Quick Excellence Kit',
    ],
    'statuses' => [
        'created' => 'Created',
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'cancelled' => 'Cancelled',
        'refunded' => 'Refunded',
    ],
    'methods' => [
        'creditcard' => 'Credit card',
        'mada' => 'mada',
        'applepay' => 'Apple Pay',
        'stcpay' => 'STC Pay',
    ],
    'receipt' => [
        'title' => 'Payment receipt',
        'number' => 'Receipt number',
        'issued_on' => 'Issue date',
        'payment_reference' => 'Payment reference',
        'entity' => 'Entity',
        'service' => 'Service',
        'price_before_vat' => 'Price before VAT',
        'vat' => 'VAT (:rate%)',
        'total' => 'Total',
        'method' => 'Payment method',
        'paid_at' => 'Paid on',
        'test_notice' => 'Test payment. This receipt has no official number and no financial value.',
        'void_notice' => 'This receipt was voided after a refund was recorded.',
        'footer' => 'This document is a payment receipt issued by the Moashir platform.',
    ],
    'export' => [
        'columns' => [
            'reference' => 'Payment reference',
            'receipt_number' => 'Receipt number',
            'created_at' => 'Created at',
            'paid_at' => 'Paid at',
            'purpose' => 'Service',
            'entity_reference' => 'Entity reference',
            'entity_name' => 'Entity',
            'entity_email' => 'Entity e-mail',
            'method' => 'Payment method',
            'status' => 'Status',
            'price_before_vat' => 'Price before VAT',
            'vat_rate' => 'VAT rate',
            'vat_amount' => 'VAT',
            'total' => 'Total',
            'currency' => 'Currency',
            'refunded_at' => 'Refunded at',
            'refund_amount' => 'Refunded amount',
        ],
    ],
];
