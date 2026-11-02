<?php

declare(strict_types=1);

use App\Enums\Finance\PaymentStatus;
use App\Exceptions\IllegalStateTransition;
use App\Models\Finance\Payment;
use App\Services\Finance\PaymentStateMachine;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

const PAYMENT_TRANSITIONS = [
    'created' => ['pending', 'cancelled'],
    'pending' => ['paid', 'failed', 'cancelled'],
    'paid' => ['refunded'],
    'failed' => [],
    'cancelled' => [],
    'refunded' => [],
];

it('allows exactly the transitions of the payment state machine', function (PaymentStatus $from, PaymentStatus $to): void {
    expect($from->allowsTransitionTo($to))->toBe(in_array($to->value, PAYMENT_TRANSITIONS[$from->value], true));
})->with(fn (): array => collect(PaymentStatus::cases())->crossJoin(PaymentStatus::cases())->mapWithKeys(
    static fn (array $pair): array => [$pair[0]->value.' to '.$pair[1]->value => $pair],
)->all());

it('never returns a cancelled order to paid', function (): void {
    expect(PaymentStatus::Cancelled->allowsTransitionTo(PaymentStatus::Paid))->toBeFalse()
        ->and(PaymentStatus::Cancelled->isConcluded())->toBeFalse()
        ->and(PaymentStatus::Refunded->hasBeenPaid())->toBeTrue()
        ->and(PaymentStatus::openValues())->toBe(['created', 'pending']);
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
]);

it('allows one open order per entity and service', function (): void {
    $payment = Payment::factory()->create();

    expect(fn () => Payment::factory()->forEntity($payment->entity)->create())->toThrow(QueryException::class);
});
