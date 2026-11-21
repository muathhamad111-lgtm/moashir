<?php

declare(strict_types=1);

use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentStatus;
use App\Exceptions\IllegalStateTransition;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentStateMachine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

const PAYMENT_TRANSITIONS = [
    'electronic' => [
        'created' => ['pending', 'cancelled'],
        'pending' => ['paid', 'failed', 'cancelled'],
        'paid' => ['refunded'],
    ],
    'bank_transfer' => [
        'under_review' => ['paid', 'rejected', 'cancelled'],
        'paid' => ['refunded'],
    ],
];

it('allows exactly the transitions of each payment channel', function (PaymentChannel $channel, PaymentStatus $from, PaymentStatus $to): void {
    expect($channel->allowsTransition($from, $to))->toBe(in_array($to->value, PAYMENT_TRANSITIONS[$channel->value][$from->value] ?? [], true));
})->with(fn (): array => collect(PaymentChannel::cases())->crossJoin(PaymentStatus::cases(), PaymentStatus::cases())->mapWithKeys(
    static fn (array $triple): array => [$triple[0]->value.': '.$triple[1]->value.' to '.$triple[2]->value => $triple],
)->all());

it('never returns a cancelled order to paid', function (): void {
    expect(PaymentChannel::Electronic->allowsTransition(PaymentStatus::Cancelled, PaymentStatus::Paid))->toBeFalse()
        ->and(PaymentStatus::Cancelled->isConcluded())->toBeFalse()
        ->and(PaymentStatus::Refunded->hasBeenPaid())->toBeTrue()
        ->and(PaymentStatus::openValues())->toBe(['created', 'pending', 'under_review']);
});

it('never reopens a rejected bank transfer and never lets a transfer skip the review', function (): void {
    expect(PaymentStatus::Rejected->isConcluded())->toBeTrue()
        ->and(PaymentStatus::Rejected->isOpen())->toBeFalse()
        ->and(PaymentChannel::BankTransfer->allowsTransition(PaymentStatus::Rejected, PaymentStatus::UnderReview))->toBeFalse()
        ->and(PaymentChannel::BankTransfer->allowsTransition(PaymentStatus::Created, PaymentStatus::Paid))->toBeFalse()
        ->and(PaymentChannel::BankTransfer->initialStatus())->toBe(PaymentStatus::UnderReview)
        ->and(PaymentChannel::Electronic->initialStatus())->toBe(PaymentStatus::Created);
});

it('stamps the rejection time when a bank transfer is rejected', function (): void {
    $payment = Payment::factory()->bankTransfer()->create();

    app(PaymentStateMachine::class)->transition($payment, PaymentStatus::Rejected, 'reject_transfer');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Rejected)
        ->and($payment->rejected_at)->not->toBeNull();
});

it('refuses gateway states on a bank-transfer payment', function (): void {
    $payment = Payment::factory()->bankTransfer()->create();

    expect(fn () => app(PaymentStateMachine::class)->transition($payment, PaymentStatus::Pending, 'bind'))->toThrow(IllegalStateTransition::class);
});

it('stamps the time of the new state and clears the checkout secret when the order closes', function (): void {
    $payment = Payment::factory()->create(['status' => PaymentStatus::Pending, 'provider_payment_id' => 'pay_0001', 'bound_at' => now()]);
    $payment->forceFill(['checkout_client_secret' => 'fake-intent-secret-1'])->save();

    app(PaymentStateMachine::class)->transition($payment, PaymentStatus::Cancelled, 'cancel');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Cancelled)
        ->and($payment->cancelled_at)->not->toBeNull()
        ->and($payment->checkout_client_secret)->toBeNull();
});

it('answers 409 for an illegal transition', function (): void {
    $payment = Payment::factory()->create();

    expect(fn () => app(PaymentStateMachine::class)->transition($payment, PaymentStatus::Refunded, 'record_refund'))
        ->toThrow(IllegalStateTransition::class);
});

it('enforces the state invariants in the database', function (array $columns): void {
    $payment = Payment::factory()->create();

    expect(fn () => DB::table('payments')->where('id', $payment->id)->update($columns))->toThrow(QueryException::class);
})->with([
    'pending without a provider payment' => [['status' => 'pending']],
    'paid without a paid date' => [['status' => 'paid', 'provider_payment_id' => 'pay_0002', 'bound_at' => now()]],
    'total that is not price plus VAT' => [['total' => '999.00']],
    'leadership purpose' => [['purpose' => 'leadership']],
    'under review on the electronic channel' => [['status' => 'under_review']],
    'electronic payment without a provider' => [['provider' => null]],
    'electronic payment without an expiry' => [['expires_at' => null]],
]);

it('enforces the bank-transfer invariants in the database', function (array $columns): void {
    $payment = Payment::factory()->bankTransfer()->create();

    expect(fn () => DB::table('payments')->where('id', $payment->id)->update($columns))->toThrow(QueryException::class);
})->with([
    'gateway status' => [['status' => 'pending']],
    'gateway provider' => [['provider' => 'moyasar']],
    'card details' => [['card_last4' => '1111']],
    'an expiry' => [['expires_at' => now()]],
    'rejected without a rejection date' => [['status' => 'rejected']],
]);

it('lets a bank-transfer payment be paid without a gateway reference', function (): void {
    $payment = Payment::factory()->bankTransfer()->create();

    DB::table('payments')->where('id', $payment->id)->update(['status' => 'paid', 'paid_at' => now()]);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Paid);
});

it('allows one open order per entity and service', function (): void {
    $payment = Payment::factory()->create();

    expect(fn () => Payment::factory()->forEntity($payment->entity)->create())->toThrow(QueryException::class);
});

it('counts a transfer under review as the open order of its service', function (): void {
    $transfer = Payment::factory()->bankTransfer()->create();

    expect(fn () => Payment::factory()->forEntity($transfer->entity)->create())->toThrow(QueryException::class);
});
