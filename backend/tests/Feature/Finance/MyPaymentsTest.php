<?php

declare(strict_types=1);

use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('lists only the own payments newest first with stats that leave test payments out', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    storedPaidPayment($entity, IntegrationMode::Live);
    storedPaidPayment($entity, IntegrationMode::Live, PaymentPurpose::Bag);
    storedPaidPayment($entity, IntegrationMode::Test);
    Payment::factory()->forEntity($entity)->state(['status' => PaymentStatus::Cancelled, 'cancelled_at' => now(), 'created_at' => now()->addMinute()])->create();
    storedPaidPayment(entityWithOwner(), IntegrationMode::Live);

    $response = switchTo($owner)->getJson('/api/v1/payments/mine')->assertOk()
        ->assertJsonPath('meta.pagination.total', 4)
        ->assertJsonPath('meta.stats', ['count' => 3, 'paid_count' => 2, 'total_paid' => '2300.00', 'total_vat' => '300.00', 'currency' => 'SAR'])
        ->assertJsonPath('data.0.status', 'cancelled');

    expect(collect($response->json('data'))->pluck('is_test')->filter()->count())->toBe(1)
        ->and(collect($response->json('data'))->every(static fn (array $item): bool => ! array_key_exists('provider_payment_id', $item) && ! array_key_exists('checkout', $item)))->toBeTrue();
});

it('hides test payments from the entity once the payment gateway runs live', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity(IntegrationMode::Live);
    storedPaidPayment($entity, IntegrationMode::Live);
    storedPaidPayment($entity, IntegrationMode::Test);

    $response = switchTo($owner)->getJson('/api/v1/payments/mine')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.status_counts.paid', 1)
        ->assertJsonPath('meta.stats.count', 1);

    expect(collect($response->json('data'))->pluck('is_test')->filter()->count())->toBe(0);
});

it('keeps test payments visible to the entity while the payment gateway runs in test mode', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity(IntegrationMode::Test);
    storedPaidPayment($entity, IntegrationMode::Live);
    storedPaidPayment($entity, IntegrationMode::Test);

    switchTo($owner)->getJson('/api/v1/payments/mine')->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('meta.status_counts.paid', 2);
});

it('filters the own payments by status and service', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    storedPaidPayment($entity, IntegrationMode::Live);
    storedPaidPayment($entity, IntegrationMode::Live, PaymentPurpose::Bag);

    switchTo($owner)->getJson('/api/v1/payments/mine?filter[purpose]=bag')->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.purpose', 'bag');
    $this->getJson('/api/v1/payments/mine?filter[status]=failed')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    $this->getJson('/api/v1/payments/mine?filter[status]=done')->assertUnprocessable();
    $this->getJson('/api/v1/payments/mine?per_page=500')->assertUnprocessable();
});

it('searches the own payments by reference, receipt number or the authorized person', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    $first = storedPaidPayment($entity, IntegrationMode::Live);
    storedPaidPayment($entity, IntegrationMode::Live, PaymentPurpose::Bag);
    storedPaidPayment(entityWithOwner(), IntegrationMode::Live);

    switchTo($owner)->getJson('/api/v1/payments/mine?search='.$first->reference)->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.id', $first->public_id);
    $this->getJson('/api/v1/payments/mine?search='.$first->receipt?->number)->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/payments/mine?search='.urlencode($owner->name))->assertOk()->assertJsonPath('meta.pagination.total', 2);
});

it('filters the own payments by channel, method and date range', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    $payment = storedPaidPayment($entity, IntegrationMode::Live);
    $day = $payment->created_at?->setTimezone(config('app.display_timezone'))->toDateString();

    switchTo($owner)->getJson('/api/v1/payments/mine?filter[channel]=bank_transfer')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    $this->getJson('/api/v1/payments/mine?filter[channel]=electronic')->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/payments/mine?filter[method]=mada&filter[status]=paid')->assertOk();
    $this->getJson("/api/v1/payments/mine?filter[from]={$day}&filter[to]={$day}")->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/payments/mine?filter[from]=2020-01-01&filter[to]=2020-01-31')->assertOk()->assertJsonPath('meta.pagination.total', 0);
});

it('rejects invalid search and filters on the own payments', function (string $query): void {
    ['owner' => $owner] = payingEntity();

    switchTo($owner)->getJson('/api/v1/payments/mine?'.$query)->assertUnprocessable();
})->with([
    'search' => ['search=%25'],
    'method' => ['filter[method]=cash'],
    'channel' => ['filter[channel]=cheque'],
    'date format' => ['filter[from]=01-11-2026'],
    'reversed range' => ['filter[from]=2026-12-01&filter[to]=2026-01-01'],
    'entity filter' => ['filter[entity_id]=5'],
]);

it('shows one own payment with its receipt summary', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    $payment = storedPaidPayment($entity, IntegrationMode::Live);

    switchTo($owner)->getJson("/api/v1/payments/{$payment->public_id}")->assertOk()
        ->assertJsonPath('data.id', $payment->public_id)
        ->assertJsonPath('data.reference', $payment->reference)
        ->assertJsonPath('data.receipt.number', $payment->receipt?->number);
});

it('keeps My Payments for entity accounts', function (): void {
    payingEntity();

    switchTo(financeOfficer())->getJson('/api/v1/payments/mine')->assertForbidden();
    switchTo(systemAdmin())->getJson('/api/v1/payments/mine')->assertForbidden();
});
