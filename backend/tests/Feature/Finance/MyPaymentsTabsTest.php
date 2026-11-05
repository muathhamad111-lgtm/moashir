<?php

declare(strict_types=1);

use App\Enums\Finance\PaymentFailureReason;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Finance\PaymentStatus;
use App\Enums\Integrations\IntegrationMode;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    Storage::fake('local');
    Notification::fake();
});

function myPayments(User $owner, string $query = ''): TestResponse
{
    return switchTo($owner)->getJson('/api/v1/payments/mine'.$query);
}

function closedPayment(Entity $entity, PaymentStatus $status, PaymentPurpose $purpose = PaymentPurpose::Accredited): Payment
{
    $payment = Payment::factory()->forEntity($entity)->create(['purpose' => $purpose]);
    $payment->forceFill(match ($status) {
        PaymentStatus::Failed => ['status' => $status, 'failed_at' => Date::now(), 'failure_reason' => PaymentFailureReason::Declined],
        default => ['status' => $status, 'cancelled_at' => Date::now()],
    })->save();

    return $payment->refresh();
}

it('filters the own payments by a list of statuses and counts every status regardless of that filter', function (): void {
    $entity = entityWithOwner();
    $owner = ownerOf($entity);
    $open = Payment::factory()->forEntity($entity)->create();
    $paid = storedPaidPayment($entity, IntegrationMode::Test);
    $cancelled = closedPayment($entity, PaymentStatus::Cancelled, PaymentPurpose::Bag);
    $failed = closedPayment($entity, PaymentStatus::Failed, PaymentPurpose::Bag);
    storedPaidPayment(entityWithOwner());
    $counts = ['created' => 1, 'pending' => 0, 'under_review' => 0, 'paid' => 1, 'failed' => 1, 'rejected' => 0, 'cancelled' => 1, 'refunded' => 0];

    $cancelledTab = myPayments($owner, '?filter[status]=cancelled,failed')->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.status_counts', $counts);
    myPayments($owner, '?filter[status]=created,pending')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $open->public_id)
        ->assertJsonPath('meta.status_counts', $counts);
    myPayments($owner, '?filter[status]=paid')->assertOk()
        ->assertJsonPath('data.0.id', $paid->public_id)
        ->assertJsonPath('data.0.is_test', true);
    myPayments($owner, '?filter[purpose]=bag')->assertOk()
        ->assertJsonPath('meta.status_counts', ['created' => 0, 'pending' => 0, 'under_review' => 0, 'paid' => 0, 'failed' => 1, 'rejected' => 0, 'cancelled' => 1, 'refunded' => 0]);

    expect(collect($cancelledTab->json('data'))->pluck('id')->sort()->values()->all())->toBe(collect([$cancelled->public_id, $failed->public_id])->sort()->values()->all());
});

it('refuses an unknown payment status in the list filter', function (): void {
    myPayments(ownerOf(entityWithOwner()), '?filter[status]=paid,settled')->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['filter.status']);
});

it('links a paid payment to the request it opened and leaves other orders without a request', function (): void {
    publishedAccreditedCriteria();
    $assessment = openedAccreditedRequest();
    $entity = $assessment->entity;
    $owner = ownerOf($entity);
    $payment = Payment::query()->whereKey($assessment->payment_id)->sole();
    $cancelled = closedPayment($entity, PaymentStatus::Cancelled);
    $request = ['id' => $assessment->id, 'number' => $assessment->request_number, 'path' => 'accredited', 'path_label' => ['ar' => 'التقييم المعتمد', 'en' => 'Accredited assessment']];

    $rows = collect(myPayments($owner)->assertOk()->json('data'))->keyBy('id');

    expect($rows->get($payment->public_id)['request'] ?? null)->toBe($request)
        ->and($rows->get($cancelled->public_id))->toHaveKey('request', null);
    switchTo($owner)->getJson("/api/v1/payments/{$payment->public_id}")->assertOk()->assertJsonPath('data.request', $request);
    switchTo($owner)->getJson("/api/v1/payments/{$cancelled->public_id}")->assertOk()->assertJsonPath('data.request', null);
    switchTo(ownerOf(entityWithOwner()))->getJson("/api/v1/payments/{$payment->public_id}")->assertNotFound();
});

it('lists the own payments with a number of queries that does not grow with the page', function (): void {
    publishedAccreditedCriteria();
    $entity = openedAccreditedRequest()->entity;
    $owner = ownerOf($entity);
    closedPayment($entity, PaymentStatus::Cancelled, PaymentPurpose::Bag);
    $before = paymentListQueryCount($owner);

    storedPaidPayment($entity);
    storedPaidPayment($entity, IntegrationMode::Test, PaymentPurpose::Bag);
    closedPayment($entity, PaymentStatus::Failed);
    Payment::factory()->forEntity($entity)->create();

    expect(paymentListQueryCount($owner))->toBe($before);
});

function paymentListQueryCount(User $owner): int
{
    myPayments($owner)->assertOk();
    DB::flushQueryLog();
    DB::enableQueryLog();
    myPayments($owner)->assertOk();
    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}
