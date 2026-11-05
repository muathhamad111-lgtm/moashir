<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Finance\PaymentRejected;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Support\RequestContext;

final readonly class ReviewerReason
{
    public function __construct(private RequestContext $context, private SecurityAlerter $alerter) {}

    public function record(User $actor, ?string $reason): void
    {
        $reason = $reason === null ? null : trim($reason);

        if ($actor->isSuperAdmin() && ($reason === null || $reason === '')) {
            throw PaymentRejected::reasonRequired();
        }

        $this->context->recordReason($reason === '' ? null : $reason);
    }

    public function alertIfSuperAdmin(User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            $this->alerter->raise(NotificationCode::ReasonRequiredAction);
        }
    }
}
