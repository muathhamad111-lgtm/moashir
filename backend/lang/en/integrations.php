<?php

declare(strict_types=1);

return [

    'defaults' => [
        'apple_pay_label' => 'Moashir Platform',
        'ai_notice' => 'The assistant answers from the platform knowledge base only. Do not share personal information.',
    ],

    'sms_templates' => [
        'otp_code' => [
            'label' => 'Verification code',
            'default_body' => 'Your Moashir verification code is {code}. It is valid for {validity}.',
        ],
    ],

    'sms_test' => [
        'body' => 'Moashir test message sent at :time.',
    ],

    'email_test' => [
        'subject' => 'Moashir e-mail settings test',
        'body' => 'This message confirms that the e-mail settings of the Moashir platform can deliver messages.',
    ],

];
