<?php

declare(strict_types=1);

use App\Enums\Assessments\SelfAssessmentStatus;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentStatusTransition;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

const SELF_PRICE = '200.00';

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
});

function pricedSelfEntity(): array
{
    $paying = payingEntity();
    publishedSelfCriteria();
    setServicePrice(PaymentPurpose::SelfAssessment, SELF_PRICE);

    return $paying;
}

it('starts a free self assessment as before', function (): void {
    publishedSelfCriteria();
    $owner = ownerOf(entityWithOwner());

    switchTo($owner)->postJson('/api/v1/assessments', ['path' => 'self'])->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.payment', null);
});

it('asks for the payment first when the self path is priced and creates nothing', function (): void {
    ['owner' => $owner] = pricedSelfEntity();

    switchTo($owner)->postJson('/api/v1/assessments', ['path' => 'self'])->assertConflict()
        ->assertJsonPath('code', 'path.payment_required')
        ->assertJsonPath('meta.path', 'self')
        ->assertJsonPath('meta.purpose', 'self')
        ->assertJsonPath('meta.pricing.price.total', '230.00')
        ->assertJsonMissingPath('meta.award_cycle_id');

    expect(Assessment::query()->count())->toBe(0);
});

it('opens the self draft on the verified card payment with the price snapshot and the awaiting payment history', function (): void {
    ['owner' => $owner, 'gateway' => $gateway, 'entity' => $entity] = pricedSelfEntity();
    $payment = openedPayment($owner, 'self');

    expect($payment->only(['price_before_vat', 'total']))->toBe(['price_before_vat' => SELF_PRICE, 'total' => '230.00']);
    expect(boardWidget(boardOf($owner, 'entity'), 'paths')['self']['awaiting_payment'])->toMatchArray(['payment_id' => $payment->public_id, 'channel' => 'electronic', 'status' => 'created'])
        ->and(boardWidget(boardOf($owner, 'entity'), 'next_step'))->toMatchArray(['rank' => 1, 'key' => 'settle_payment', 'path' => 'self'])
        ->and(boardWidget(boardOf($owner, 'entity'), 'next_step')['context'])->toMatchArray([
            'payment_id' => $payment->public_id,
            'payment_reference' => $payment->reference,
            'payment_channel' => 'electronic',
            'payment_status' => 'created',
            'payment_expires_at' => $payment->expires_at?->toIso8601String(),
        ]);

    setServicePrice(PaymentPurpose::SelfAssessment, '999.00');
    verifyAs($owner, $payment, providerPaymentOf($gateway, $payment))->assertOk()->assertJsonPath('data.status', 'paid');

    $draft = Assessment::query()->where('payment_id', $payment->id)->sole();
    expect($draft->status)->toBe(SelfAssessmentStatus::Draft)
        ->and($draft->entity_id)->toBe($entity->id)
        ->and($payment->refresh()->total)->toBe('230.00')
        ->and(AssessmentStatusTransition::query()->where('assessment_id', $draft->id)->orderBy('id')->pluck('to_status')->all())->toBe(['awaiting_payment', 'draft']);

    switchTo($owner)->getJson("/api/v1/payments/{$payment->public_id}")->assertOk()
        ->assertJsonPath('data.purpose', 'self')
        ->assertJsonPath('data.award_cycle', null)
        ->assertJsonPath('data.request.id', $draft->id);
    switchTo($owner)->getJson("/api/v1/assessments/{$draft->id}")->assertOk()->assertJsonPath('data.payment.reference', $payment->reference);
    $stranger = ownerOf(entityWithOwner());
    switchTo($stranger)->getJson("/api/v1/payments/{$payment->public_id}")->assertNotFound();
    switchTo($stranger)->getJson("/api/v1/assessments/{$draft->id}")->assertNotFound();

    expect(boardWidget(boardOf($owner, 'entity'), 'next_step'))->toMatchArray(['rank' => 3, 'key' => 'finish_draft', 'path' => 'self'])
        ->and(boardWidget(boardOf($owner, 'entity'), 'paths')['self']['awaiting_payment'])->toBeNull();
});

it('refuses a second self order while a draft or an order is open', function (): void {
    ['owner' => $owner, 'gateway' => $gateway] = pricedSelfEntity();
    $payment = paidPayment($owner, $gateway, 'self');
    $draftId = Assessment::query()->where('payment_id', $payment->id)->value('id');

    startPayment($owner, 'self')->assertConflict()->assertJsonPath('code', 'self.draft_exists')->assertJsonPath('meta.assessment_id', $draftId);
});

it('closes the open self draft when a refund is recorded before submission', function (): void {
    ['owner' => $owner, 'gateway' => $gateway] = pricedSelfEntity();
    $payment = paidPayment($owner, $gateway, 'self');

    switchTo(financeOfficer())->post("/api/v1/finance/payments/{$payment->public_id}/refunds", refundPayload($payment), ['Accept' => 'application/json'])->assertCreated();

    $draft = Assessment::query()->where('payment_id', $payment->id)->sole();
    expect($draft->status)->toBe(SelfAssessmentStatus::Refunded)
        ->and($draft->refunded_at)->not->toBeNull()
        ->and($payment->refresh()->status->value)->toBe('refunded');

    switchTo($owner)->getJson('/api/v1/assessments?filter[path]=self')->assertOk()->assertJsonPath('data.0.status', 'refunded');
});

it('refuses a self payment while the self path is free', function (): void {
    ['owner' => $owner] = payingEntity();
    publishedSelfCriteria();

    startPayment($owner, 'self')->assertConflict()->assertJsonPath('code', 'payment.not_required')->assertJsonPath('meta.purpose', 'self');
});

it('quotes the self price on the checkout options', function (): void {
    ['owner' => $owner] = pricedSelfEntity();

    switchTo($owner)->getJson('/api/v1/payments/checkout-options?purpose=self')->assertOk()
        ->assertJsonPath('data.pricing.price.total', '230.00');
    switchTo($owner)->getJson('/api/v1/payments/checkout-options')->assertOk()->assertJsonPath('data.pricing', null);
});
