<?php

declare(strict_types=1);

use App\Contracts\Integrations\PaymentGateway;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Region;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

const AWARD_FEE = '500.00';

const AWARD_FEE_TOTAL = '575.00';

beforeEach(function (): void {
    Notification::fake();
    Storage::fake('local');
});

/**
 * @return array{cycle: AwardCycle, owner: User}
 */
function feeCycleWithEntity(): array
{
    fakePaymentGateway();
    activatePaymentGateway();
    setServicePrices();
    $cycle = openAwardCycle(publishedAccreditedCriteria(), ['entry_fee' => AWARD_FEE]);

    return ['cycle' => $cycle, 'owner' => ownerOf(cycleEntity($cycle))];
}

/**
 * @param  array<string, mixed>  $payload
 */
function startAwardPayment(User $owner, AwardCycle $cycle, array $payload = []): TestResponse
{
    return switchTo($owner)->postJson('/api/v1/payments', ['purpose' => 'award', 'award_cycle_id' => $cycle->id, ...$payload], ['Idempotency-Key' => (string) Str::uuid()]);
}

it('sets the cycle entry fee from the award default price and locks it once the submission window opens', function (): void {
    Date::setTestNow('2026-11-01 08:00:00');
    $version = publishedAccreditedCriteria();
    setServicePrice(PaymentPurpose::Award, '300.00');
    $admin = systemAdmin();

    switchTo($admin)->getJson('/api/v1/award/cycles/default-conditions')->assertOk()->assertJsonPath('data.entry_fee', '300.00');
    $id = switchTo($admin)->postJson('/api/v1/award/cycles', cyclePayload($version, Region::factory()->create()))->assertCreated()
        ->assertJsonPath('data.entry_fee', '300.00')
        ->assertJsonPath('data.entry_fee_quote.total', '345.00')
        ->assertJsonPath('data.entry_fee_editable', true)
        ->json('data.id');

    switchTo($admin)->putJson("/api/v1/award/cycles/{$id}", ['entry_fee' => '0.00'])->assertOk()->assertJsonPath('data.entry_fee', '0.00');
    switchTo($admin)->putJson("/api/v1/award/cycles/{$id}", ['entry_fee' => '12.5'])->assertUnprocessable()->assertJsonValidationErrors(['entry_fee']);

    setServicePrice(PaymentPurpose::Award, '900.00');
    expect(AwardCycle::query()->findOrFail($id)->entry_fee)->toBe('0.00');

    AwardCycle::query()->whereKey($id)->update(['status' => 'submission_open', 'opened_at' => Date::now()]);
    switchTo($admin)->putJson("/api/v1/award/cycles/{$id}", ['entry_fee' => '100.00'])->assertConflict()
        ->assertJsonPath('code', 'cycle.field_locked')
        ->assertJsonPath('meta.locked_fields', ['entry_fee']);
    switchTo($admin)->getJson("/api/v1/award/cycles/{$id}")->assertOk()->assertJsonPath('data.entry_fee_editable', false);
});

it('checks eligibility first, then asks for the fee, and opens the draft in that cycle on the verified card payment', function (): void {
    ['cycle' => $cycle, 'owner' => $owner] = feeCycleWithEntity();

    applyForCycle($owner, $cycle)->assertConflict()
        ->assertJsonPath('code', 'path.payment_required')
        ->assertJsonPath('meta.award_cycle_id', $cycle->id)
        ->assertJsonPath('meta.pricing.price.total', AWARD_FEE_TOTAL);
    expect(boardWidget(boardOf($owner, 'entity'), 'paths')['award'])->toMatchArray(['pricing' => ['price' => ['price_before_vat' => AWARD_FEE, 'vat_rate' => '15.00', 'vat_amount' => '75.00', 'total' => AWARD_FEE_TOTAL, 'currency' => 'SAR'], 'is_free' => false, 'availability' => 'available']]);

    switchTo($owner)->postJson('/api/v1/payments', ['purpose' => 'award'], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors(['award_cycle_id']);
    switchTo($owner)->postJson('/api/v1/payments', ['purpose' => 'accredited', 'award_cycle_id' => $cycle->id], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable()->assertJsonValidationErrors(['award_cycle_id']);

    $created = startAwardPayment($owner, $cycle)->assertCreated()
        ->assertJsonPath('data.purpose', 'award')
        ->assertJsonPath('data.award_cycle', ['id' => $cycle->id, 'name' => $cycle->name->toArray()])
        ->assertJsonPath('data.pricing.total', AWARD_FEE_TOTAL);
    $payment = Payment::query()->where('public_id', $created->json('data.id'))->sole();

    startAwardPayment($owner, $cycle)->assertOk()->assertJsonPath('meta.existing_order', true);
    applyForCycle($owner, $cycle)->assertConflict()->assertJsonPath('code', 'payment.open_order_exists');
    $award = boardWidget(boardOf($owner, 'entity'), 'paths')['award'];
    expect($award['awaiting_payment']['payment_id'])->toBe($payment->public_id)
        ->and($award['eligibility'])->toBe(['eligible' => false, 'reason' => 'awaiting_payment']);

    $cycle->forceFill(['entry_fee' => '0.00'])->save();
    verifyAs($owner, $payment, providerPaymentOf(app(PaymentGateway::class), $payment))->assertOk()->assertJsonPath('data.status', 'paid');

    $draft = Assessment::query()->where('payment_id', $payment->id)->sole();
    expect($draft->status)->toBe(AdvancedAssessmentStatus::Draft)
        ->and($draft->award_cycle_id)->toBe($cycle->id)
        ->and($payment->refresh()->total)->toBe(AWARD_FEE_TOTAL);

    switchTo($owner)->getJson('/api/v1/assessments?filter[path]=advanced')->assertOk()
        ->assertJsonPath('data.0.award_cycle', ['id' => $cycle->id, 'name' => $cycle->name->toArray()])
        ->assertJsonPath('data.0.payment.reference', $payment->reference);
    $award = boardWidget(boardOf($owner, 'entity'), 'paths')['award'];
    expect($award['open_request']['payment']['status'])->toBe('paid')
        ->and($award['window_closed_before_payment'])->toBeFalse();
});

it('charges nothing when the conditions fail and records the rejection as built', function (): void {
    ['cycle' => $cycle] = feeCycleWithEntity();
    $young = ownerOf(cycleEntity($cycle, (int) Date::now()->year));

    startAwardPayment($young, $cycle)->assertUnprocessable()->assertJsonPath('code', 'cycle.conditions_failed');

    expect(Payment::query()->where('purpose', PaymentPurpose::Award)->count())->toBe(0)
        ->and(Assessment::query()->where('status', AdvancedAssessmentStatus::RejectedConditions)->count())->toBe(1);
});

it('opens the draft for a transfer approved after the window, flags it and lists it for a recorded refund', function (): void {
    ['cycle' => $cycle, 'owner' => $owner] = feeCycleWithEntity();
    $account = bankAccount();
    enablePaymentChannels(PaymentChannel::Electronic, PaymentChannel::BankTransfer);
    $id = submitBankTransfer($owner, $account, ['purpose' => 'award', 'award_cycle_id' => $cycle->id])->assertCreated()->json('data.id');
    $payment = Payment::query()->where('public_id', $id)->sole();

    closeCycleSubmissions($cycle);
    approveTransfer(transferReviewer(), $payment)->assertOk();

    $draft = Assessment::query()->where('payment_id', $payment->id)->sole();
    expect($draft->status)->toBe(AdvancedAssessmentStatus::Draft)
        ->and(boardWidget(boardOf($owner, 'entity'), 'paths')['award']['window_closed_before_payment'])->toBeTrue()
        ->and(actionItems(boardOf(financeOfficer()))->firstWhere('key', 'award_paid_after_window'))->toMatchArray(['count' => 1, 'link' => '/finance/transactions?purpose=award&status=paid']);

    switchTo($owner)->postJson("/api/v1/assessments/{$draft->id}/submit")->assertConflict()->assertJsonPath('code', 'cycle.submission_closed');
});

it('closes an award draft when its fee refund is recorded', function (): void {
    ['cycle' => $cycle, 'owner' => $owner] = feeCycleWithEntity();
    $id = startAwardPayment($owner, $cycle)->assertCreated()->json('data.id');
    $payment = Payment::query()->where('public_id', $id)->sole();
    verifyAs($owner, $payment, providerPaymentOf(app(PaymentGateway::class), $payment))->assertOk();

    switchTo(financeOfficer())->post("/api/v1/finance/payments/{$payment->public_id}/refunds", refundPayload($payment->refresh()), ['Accept' => 'application/json'])->assertCreated();

    $draft = Assessment::query()->where('payment_id', $payment->id)->sole();
    expect($draft->status)->toBe(AdvancedAssessmentStatus::Refunded)
        ->and($draft->refunded_at)->not->toBeNull();
});

it('guards one open award order per entity and cycle in the database', function (): void {
    ['cycle' => $cycle, 'owner' => $owner] = feeCycleWithEntity();
    $factory = Payment::factory()->state(['entity_id' => $owner->entity_id, 'purpose' => PaymentPurpose::Award, 'award_cycle_id' => $cycle->id, 'status' => PaymentStatus::Created]);
    $factory->create();

    expect(fn () => DB::transaction(fn () => $factory->create()))->toThrow(QueryException::class, 'payments_one_open_award_order')
        ->and(fn () => DB::transaction(fn () => Payment::factory()->state(['entity_id' => $owner->entity_id, 'purpose' => PaymentPurpose::Award, 'award_cycle_id' => null])->create()))
        ->toThrow(QueryException::class, 'payments_award_cycle_check');
});

it('opens a free award draft directly as built', function (): void {
    $version = publishedAccreditedCriteria();
    $cycle = openAwardCycle($version);

    applyForCycle(ownerOf(cycleEntity($cycle)), $cycle)->assertCreated()->assertJsonPath('data.status', 'draft')->assertJsonPath('data.award_cycle.id', $cycle->id);
});
