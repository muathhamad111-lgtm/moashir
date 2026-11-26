<?php

declare(strict_types=1);

use App\Enums\Finance\ReceiptStatus;
use App\Models\Finance\PaymentReceipt;
use App\Services\Finance\ReceiptDocumentBuilder;
use Carbon\CarbonImmutable;

function unsavedReceiptPaidAt(?string $paidAt): PaymentReceipt
{
    return (new PaymentReceipt)->forceFill([
        'number' => 'RCP-2026-000031',
        'is_test' => false,
        'status' => ReceiptStatus::Issued,
        'issued_at' => CarbonImmutable::parse('2026-11-24T07:00:00Z'),
        'snapshot' => [
            'payment_reference' => 'PAY-2026-000031',
            'purpose' => 'bag',
            'currency' => 'SAR',
            'channel' => 'electronic',
            'paid_at' => $paidAt,
        ],
    ]);
}

it('gives the payment day in both calendars in the display time zone', function (): void {
    $data = app(ReceiptDocumentBuilder::class)->for(unsavedReceiptPaidAt('2026-11-23T22:30:00Z'))->data;

    expect($data['paid_on'])->toBe('2026-11-24')
        ->and($data['paid_on_hijri'])->toBe(['ar' => '14 جمادى الآخرة 1448', 'en' => '14 Jumada II 1448']);
});

it('leaves the payment day empty in both calendars without a payment time', function (?string $paidAt): void {
    $data = app(ReceiptDocumentBuilder::class)->for(unsavedReceiptPaidAt($paidAt))->data;

    expect($data['paid_on'])->toBeNull()
        ->and($data['paid_on_hijri'])->toBeNull();
})->with([
    'no payment time' => [null],
    'an empty payment time' => [''],
]);
