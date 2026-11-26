<?php

declare(strict_types=1);

namespace App\Services\Prices;

use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Settings\SettingsRejected;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Support\RequestContext;

final readonly class PriceEditAuthority
{
    public function __construct(private RequestContext $context, private SecurityAlerter $alerter) {}

    public function recordReason(User $actor, ?string $reason): void
    {
        if ($actor->isSuperAdmin() && ($reason === null || $reason === '')) {
            throw SettingsRejected::reasonRequired();
        }

        $this->context->recordReason($reason);
    }

    public function afterChange(User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            $this->alerter->raise(NotificationCode::ReasonRequiredAction);
        }
    }
}
