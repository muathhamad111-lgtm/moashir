<?php

declare(strict_types=1);

namespace App\Listeners\Finance;

use App\Contracts\Finance\OpenedRequestLookup;
use App\Data\Entities\StaffAudience;
use App\Data\Finance\OpenedRequest;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentPurpose;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Finance\PaymentPaid;
use App\Models\Finance\Payment;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentLinks;
use App\Support\Finance\FinanceLinks;
use App\Support\Finance\FinanceNotices;

final readonly class NotifyPaymentPaid
{
    private const array STAFF_ROLES = [RoleKey::RequestFollower, RoleKey::SystemAdmin];

    private const NotificationCode CODE = NotificationCode::PaymentSucceeded;

    public function __construct(private EntityNotifier $notifier, private OpenedRequestLookup $openedRequests) {}

    public function handle(PaymentPaid $event): void
    {
        $payment = $event->payment;
        $finance = PermissionKey::FinanceOperationsView;
        $queue = PermissionKey::RequestsQueueView;

        $this->notifier->toOwner($payment->entity, FinanceNotices::forPayment(self::CODE, FinanceNotices::FOR_ENTITY, $payment));
        $this->notifier->toAudience(new StaffAudience(self::STAFF_ROLES, [$finance]), FinanceNotices::forStaffWithLink(self::CODE, $payment, FinanceLinks::transaction($payment->public_id)));
        $this->notifier->toAudience(new StaffAudience(self::STAFF_ROLES, [$queue], [$finance]), FinanceNotices::forStaffWithLink(self::CODE, $payment, $this->requestLinkOf($payment)));
        $this->notifier->toAudience(new StaffAudience(self::STAFF_ROLES, [], [$finance, $queue]), FinanceNotices::forStaffWithLink(self::CODE, $payment, null));
    }

    private function requestLinkOf(Payment $payment): ?string
    {
        if ($payment->purpose !== PaymentPurpose::Accredited) {
            return null;
        }

        $opened = $this->openedRequests->forPayments([$payment->id])[$payment->id] ?? null;

        return $opened instanceof OpenedRequest ? AssessmentLinks::request($opened->id) : AssessmentLinks::preparingRequests(AssessmentPath::Accredited);
    }
}
