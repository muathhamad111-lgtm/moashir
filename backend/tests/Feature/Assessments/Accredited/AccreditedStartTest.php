<?php

declare(strict_types=1);

use App\Actions\Assessments\OpenPaidAccreditedRequest;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\PaymentPaid;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentStatusTransition;
use App\Models\Audit\Audit;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
});

it('opens the accredited draft when its payment is confirmed paid', function (): void {
    $version = publishedAccreditedCriteria();
    ['gateway' => $gateway, 'owner' => $owner, 'entity' => $entity] = payingEntity();
    $payment = paidPayment($owner, $gateway);
    $year = Date::now('Asia/Riyadh')->year;

    $assessment = Assessment::query()->where('payment_id', $payment->id)->firstOrFail();

    expect($assessment->request_number)->toBe(sprintf('ACC-%d-000001', $year))
        ->and($assessment->path->value)->toBe('accredited')
        ->and($assessment->status->value)->toBe('draft')
        ->and($assessment->entity_id)->toBe($entity->id)
        ->and($assessment->criteria_version_id)->toBe($version->id)
        ->and(AssessmentStatusTransition::query()->where('assessment_id', $assessment->id)->orderBy('id')->get(['from_status', 'to_status', 'verb', 'actor_user_id'])->toArray())->toBe([
            ['from_status' => null, 'to_status' => 'awaiting_payment', 'verb' => 'start', 'actor_user_id' => null],
            ['from_status' => 'awaiting_payment', 'to_status' => 'draft', 'verb' => 'open_draft', 'actor_user_id' => null],
        ])
        ->and(Audit::query()->where('event', AuditEvent::AssessmentOpened->value)->where('auditable_id', $assessment->id)->exists())->toBeTrue()
        ->and(financeNoticeSent($owner, NotificationCode::AccreditedFormOpened))->toBeTrue();
});

it('opens one request only when the paid event is delivered twice', function (): void {
    publishedAccreditedCriteria();
    $payment = storedPaidPayment(entityWithOwner(), IntegrationMode::Live);

    PaymentPaid::dispatch($payment);
    PaymentPaid::dispatch($payment);
    app(OpenPaidAccreditedRequest::class)->handle($payment->id);

    expect(Assessment::query()->where('payment_id', $payment->id)->count())->toBe(1)
        ->and(Assessment::query()->where('entity_id', $payment->entity_id)->count())->toBe(1);
});

it('ignores Kit payments and unpaid orders', function (): void {
    publishedAccreditedCriteria();
    $bag = storedPaidPayment(entityWithOwner(), IntegrationMode::Live, PaymentPurpose::Bag);

    PaymentPaid::dispatch($bag);

    expect(Assessment::query()->count())->toBe(0);
});

it('refuses a new accredited order while the entity has an open accredited request', function (): void {
    publishedAccreditedCriteria();
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();
    paidPayment($owner, $gateway);
    $open = Assessment::query()->firstOrFail();

    startPayment($owner)->assertConflict()
        ->assertJsonPath('code', 'accredited.request_open')
        ->assertJsonPath('meta.assessment_id', $open->id);
    startPayment($owner, 'bag')->assertCreated();
});

it('alerts finance instead of opening a second request for a duplicate paid payment', function (): void {
    publishedAccreditedCriteria();
    $entity = entityWithOwner();
    $first = openedAccreditedRequest($entity);
    $duplicate = storedPaidPayment($entity, IntegrationMode::Live);
    $financeOfficer = financeOfficer();

    expect(app(OpenPaidAccreditedRequest::class)->handle($duplicate->id))->toBeNull()
        ->and(Assessment::query()->where('entity_id', $entity->id)->pluck('id')->all())->toBe([$first->id])
        ->and(Audit::query()->where('event', AuditEvent::AssessmentDuplicatePayment->value)->where('auditable_id', $duplicate->id)->exists())->toBeTrue()
        ->and(financeNoticeSent($financeOfficer, NotificationCode::PaymentRefundRequired))->toBeTrue();
});

it('alerts the System Administrators when no criteria are published and opens the request later', function (): void {
    $payment = storedPaidPayment(entityWithOwner(), IntegrationMode::Live);
    $admin = systemAdmin();
    $payment->forceFill(['paid_at' => Date::now()->subMinutes(30)])->save();

    PaymentPaid::dispatch($payment);

    expect(Assessment::query()->count())->toBe(0)
        ->and(financeNoticeSent($admin, NotificationCode::RequestProcessingFailed))->toBeTrue();

    publishedAccreditedCriteria();
    Artisan::call('moashir:assessments:open-paid-requests');

    expect(Assessment::query()->where('payment_id', $payment->id)->firstOrFail()->status->value)->toBe('draft');
});

it('refuses a new accredited order while a paid payment still waits for its request', function (): void {
    ['owner' => $owner, 'entity' => $entity] = payingEntity();
    storedPaidPayment($entity, IntegrationMode::Live);

    startPayment($owner)->assertConflict()->assertJsonPath('code', 'accredited.payment_being_processed');
});

it('shows the current accredited request of the entity', function (): void {
    $version = publishedAccreditedCriteria();
    ['gateway' => $gateway, 'owner' => $owner] = payingEntity();

    switchTo($owner)->getJson('/api/v1/assessments/accredited/current')->assertOk()
        ->assertJsonPath('data.awaiting_payment', null)
        ->assertJsonPath('data.active', null);

    $order = openedPayment($owner);

    switchTo($owner)->getJson('/api/v1/assessments/accredited/current')->assertOk()
        ->assertJsonPath('data.awaiting_payment.payment_id', $order->public_id)
        ->assertJsonPath('data.awaiting_payment.reference', $order->reference);

    verifyAs($owner, $order, providerPaymentOf($gateway, $order))->assertOk();

    switchTo($owner)->getJson('/api/v1/assessments/accredited/current')->assertOk()
        ->assertJsonPath('data.awaiting_payment', null)
        ->assertJsonPath('data.active.status', 'draft')
        ->assertJsonPath('data.active.path', 'accredited')
        ->assertJsonPath('data.active.criteria_version_id', $version->id)
        ->assertJsonPath('data.latest_closed', null);
});

it('keeps the current accredited request endpoint for entity accounts', function (): void {
    switchTo(systemAdmin())->getJson('/api/v1/assessments/accredited/current')->assertForbidden();
    switchTo(assessorUser())->getJson('/api/v1/assessments/accredited/current')->assertForbidden();
    test()->flushSession();
    app('auth')->forgetGuards();
    test()->getJson('/api/v1/assessments/accredited/current')->assertUnauthorized();
});
