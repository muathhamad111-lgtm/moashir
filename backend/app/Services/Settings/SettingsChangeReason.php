<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Settings\SettingsRejected;
use App\Models\Identity\User;
use App\Services\Notifications\SecurityAlerter;
use App\Support\RequestContext;

final readonly class SettingsChangeReason
{
    public function __construct(private RequestContext $context, private SecurityAlerter $alerter) {}

    public function record(User $actor, ?string $reason): void
    {
        $reason = $reason === null ? null : trim($reason);

        if ($actor->isSuperAdmin() && ($reason === null || $reason === '')) {
            throw SettingsRejected::reasonRequired();
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
