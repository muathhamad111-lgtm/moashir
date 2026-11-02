<?php

declare(strict_types=1);

use App\Enums\Identity\RoleKey;
use App\Models\Finance\CheckoutIntent;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

it('shows the payment operations log to System Administrators without payloads and with masked references', function (): void {
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    $payment = openedPayment($owner);
    $providerId = providerPaymentOf($gateway, $payment);
    sendMoyasarWebhook(moyasarEventFor($payment, $providerId))->assertOk();

    $response = switchTo(systemAdmin())->getJson('/api/v1/finance/payment-events')->assertOk()
        ->assertJsonPath('meta.pagination.total', 2);
    $webhook = collect($response->json('data'))->firstWhere('source', 'webhook');

    expect($webhook['payment'])->toBe(['id' => $payment->public_id, 'reference' => $payment->reference])
        ->and($webhook['result'])->toBe('applied')
        ->and($webhook['signature_valid'])->toBeTrue()
        ->and($webhook['provider_ref'])->toBe('…'.substr($providerId, -6))
        ->and($webhook)->not->toHaveKey('payload')
        ->and((string) $response->getContent())->not->toContain($providerId);

    $this->getJson('/api/v1/finance/payment-events?filter[source]=checkout')->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/finance/payment-events?search='.$payment->reference)->assertOk()->assertJsonPath('meta.pagination.total', 2);
    $this->getJson('/api/v1/finance/payment-events?filter[result]=unknown')->assertUnprocessable();
});

it('keeps the payment operations log for System Administrators', function (): void {
    foreach ([financeOfficer(), staffUser(RoleKey::RequestFollower), ownerOf(entityWithOwner())] as $user) {
        switchTo($user)->getJson('/api/v1/finance/payment-events')->assertForbidden();
    }

    switchTo(superAdmin())->getJson('/api/v1/finance/payment-events')->assertOk();
});

it('lists abandoned carts older than the configured delay with masked contacts', function (): void {
    ['owner' => $owner, 'entity' => $entity, 'gateway' => $gateway] = payingEntity();
    openedPayment($owner);
    $converted = entityWithOwner();
    $convertedOwner = ownerOf($converted);
    paidPayment($convertedOwner, $gateway, 'bag');

    switchTo(systemAdmin())->getJson('/api/v1/messaging/abandoned-carts')->assertOk()->assertJsonPath('meta.pagination.total', 0);

    CheckoutIntent::query()->update(['last_seen_at' => now()->subHours(25)]);
    $response = $this->getJson('/api/v1/messaging/abandoned-carts')->assertOk()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('data.0.entity.id', $entity->id)
        ->assertJsonPath('data.0.purpose', 'accredited')
        ->assertJsonPath('data.0.marketing_consent', false);

    expect($response->json('data.0.email'))->toContain('***@')
        ->and($response->json('data.0.email'))->not->toBe($owner->email)
        ->and(CheckoutIntent::query()->whereNotNull('converted_payment_id')->count())->toBe(1);

    $this->getJson('/api/v1/messaging/abandoned-carts?filter[purpose]=bag')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    $this->getJson('/api/v1/messaging/abandoned-carts?filter[contacted]=false')->assertOk()->assertJsonPath('meta.pagination.total', 1);
    $this->getJson('/api/v1/messaging/abandoned-carts?filter[contacted]=maybe')->assertUnprocessable();
});

it('shows abandoned carts only to holders of the campaigns permission', function (): void {
    foreach ([financeOfficer(), staffUser(RoleKey::ContentOfficer), ownerOf(entityWithOwner())] as $user) {
        switchTo($user)->getJson('/api/v1/messaging/abandoned-carts')->assertForbidden();
    }
});

it('removes the abandoned-cart traces of a permanently deleted entity and keeps its payments', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    openedPayment($owner);
    $entity->delete();

    switchTo(superAdmin())->deleteJson("/api/v1/entities/{$entity->id}/permanent", [
        'reason' => 'طلب الجهة حذف بياناتها نهائيًا وفق نظام حماية البيانات الشخصية.',
        'confirmation' => $entity->reference(),
        'disclaimer_acknowledged' => true,
    ])->assertOk();

    expect(CheckoutIntent::query()->where('entity_id', $entity->id)->count())->toBe(0)
        ->and(Payment::query()->where('entity_id', $entity->id)->count())->toBe(1);
});
