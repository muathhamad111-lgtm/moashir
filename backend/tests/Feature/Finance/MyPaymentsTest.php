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

it('filters the own payments by status and service', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    storedPaidPayment($entity, IntegrationMode::Live);
    storedPaidPayment($entity, IntegrationMode::Live, PaymentPurpose::Bag);

    switchTo($owner)->getJson('/api/v1/payments/mine?filter[purpose]=bag')->assertOk()->assertJsonPath('meta.pagination.total', 1)->assertJsonPath('data.0.purpose', 'bag');
    $this->getJson('/api/v1/payments/mine?filter[status]=failed')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    $this->getJson('/api/v1/payments/mine?filter[status]=done')->assertUnprocessable();
    $this->getJson('/api/v1/payments/mine?per_page=500')->assertUnprocessable();
});

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
