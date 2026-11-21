<?php

declare(strict_types=1);

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\PaymentPaid;
use App\Listeners\Finance\NotifyPaymentPaid;
use App\Models\Finance\Payment;
use App\Models\Identity\Role;
use App\Notifications\Entities\EntityNotification;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Finance\FinanceLinks;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    Notification::fake();
});

/**
 * @return list<string|null>
 */
function paymentPaidLinksOf(object $notifiable): array
{
    $links = [];

    Notification::assertSentTo($notifiable, EntityNotification::class, static function (EntityNotification $notification) use (&$links): bool {
        if ($notification->notice->code === NotificationCode::PaymentSucceeded) {
            $links[] = $notification->notice->link;
        }

        return true;
    });

    return $links;
}

function announcePaymentPaid(Payment $payment): void
{
    app(NotifyPaymentPaid::class)->handle(new PaymentPaid($payment->load('entity')));
}

it('links the follower to the opened request and the System Administrator to the finance record', function (): void {
    publishedAccreditedCriteria();
    $follower = requestFollower();
    $admin = systemAdmin();
    $assessment = openedAccreditedRequest();
    $payment = $assessment->payment()->firstOrFail();
    Notification::fake();

    announcePaymentPaid($payment);

    expect(paymentPaidLinksOf($follower))->toBe([AssessmentLinks::request($assessment->id)])
        ->and(paymentPaidLinksOf($admin))->toBe([FinanceLinks::transaction($payment->public_id)]);
});

it('links the follower to the paid drafts tab while the request is not opened yet', function (): void {
    $follower = requestFollower();
    $payment = storedPaidPayment(entityWithOwner());

    announcePaymentPaid($payment);

    expect(paymentPaidLinksOf($follower))->toBe([AssessmentLinks::preparingRequests(AssessmentPath::Accredited)])
        ->and(AssessmentLinks::preparingRequests(AssessmentPath::Accredited))->toBe('/admin/requests?path=accredited&stage=preparing');
});

it('gives the finance record to a follower who also holds the finance permission, once', function (): void {
    $follower = staffUser(RoleKey::RequestFollower, RoleKey::SystemAdmin);
    $payment = storedPaidPayment(entityWithOwner());

    announcePaymentPaid($payment);

    expect(paymentPaidLinksOf($follower))->toBe([FinanceLinks::transaction($payment->public_id)]);
});

it('never gives a follower without the finance permission a finance link', function (): void {
    $follower = requestFollower();
    $payment = storedPaidPayment(entityWithOwner(), purpose: PaymentPurpose::Bag);

    announcePaymentPaid($payment);

    expect(paymentPaidLinksOf($follower))->toBe([null]);
});

it('sends no link to a recipient who can open neither the request nor the finance record', function (): void {
    $admin = systemAdmin();
    $role = Role::findByName(RoleKey::SystemAdmin->value);
    $role->revokePermissionTo(PermissionKey::FinanceOperationsView->value);
    $role->revokePermissionTo(PermissionKey::RequestsQueueView->value);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $payment = storedPaidPayment(entityWithOwner());

    announcePaymentPaid($payment);

    expect(paymentPaidLinksOf($admin->refresh()))->toBe([null]);
});

it('keeps the Finance Officer out of the paid notice', function (): void {
    $finance = financeOfficer();

    announcePaymentPaid(storedPaidPayment(entityWithOwner()));

    Notification::assertNotSentTo($finance, EntityNotification::class);
});
