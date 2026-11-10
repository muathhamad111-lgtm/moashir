<?php

declare(strict_types=1);

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Finance\PaymentPurpose;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;
use Illuminate\Support\Facades\Notification;

const ACCREDITED_START = '/api/v1/assessments/accredited';

beforeEach(function (): void {
    Notification::fake();
    atRiyadh('2027-02-10 10:00');
    livePeriods();
});

it('opens a free accredited request directly as a draft with no payment anywhere', function (): void {
    publishedAccreditedCriteria();
    setServicePrice(PaymentPurpose::Accredited, '0.00');
    $owner = ownerOf(entityWithOwner());

    $id = switchTo($owner)->postJson(ACCREDITED_START)->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.payment', null)
        ->assertJsonPath('data.period_state', 'pending')
        ->json('data.id');

    $request = Assessment::query()->findOrFail($id);
    expect($request->status)->toBe(AccreditedAssessmentStatus::Draft)
        ->and($request->payment_id)->toBeNull()
        ->and(Payment::query()->count())->toBe(0);

    $step = boardWidget(boardOf($owner, 'entity'), 'next_step');
    expect($step)->toMatchArray(['rank' => 3, 'key' => 'finish_draft'])
        ->and($step['context']['is_paid'])->toBeFalse();

    switchTo($owner)->getJson('/api/v1/assessments/accredited/current')->assertOk()
        ->assertJsonPath('data.pricing', ['price' => null, 'is_free' => true, 'availability' => 'available'])
        ->assertJsonPath('data.active.payment', null);
    switchTo($owner)->getJson("/api/v1/assessments/{$id}")->assertOk()->assertJsonPath('data.payment', null);
    switchTo(systemAdmin())->getJson('/api/v1/requests/preparing')->assertOk();
    switchTo(systemAdmin())->getJson('/api/v1/finance/summary')->assertOk();

    switchTo($owner)->postJson(ACCREDITED_START)->assertConflict()->assertJsonPath('code', 'accredited.request_open')->assertJsonPath('meta.assessment_id', $id);
});

it('asks for the payment first when the accredited path is priced', function (): void {
    publishedAccreditedCriteria();
    setServicePrices('1000.00');

    switchTo(ownerOf(entityWithOwner()))->postJson(ACCREDITED_START)->assertConflict()
        ->assertJsonPath('code', 'path.payment_required')
        ->assertJsonPath('meta.pricing.price.total', '1150.00');

    expect(Assessment::query()->count())->toBe(0);
});

it('refuses an order for a free path', function (): void {
    ['owner' => $owner] = payingEntity();
    setServicePrice(PaymentPurpose::Accredited, '0.00');

    startPayment($owner)->assertConflict()->assertJsonPath('code', 'payment.not_required')->assertJsonPath('meta.purpose', 'accredited');
});

it('denies the free start to staff and to entities without the accredited permission', function (): void {
    setServicePrice(PaymentPurpose::Accredited, '0.00');

    switchTo(systemAdmin())->postJson(ACCREDITED_START)->assertForbidden();
    test()->postJson(ACCREDITED_START)->assertForbidden();
    expect(Assessment::query()->count())->toBe(0);
});
