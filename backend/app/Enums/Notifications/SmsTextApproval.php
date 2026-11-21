<?php

declare(strict_types=1);

namespace App\Enums\Notifications;

enum SmsTextApproval: string
{
    case NotRequired = 'not_required';
    case Approved = 'approved';
    case Pending = 'pending';
    case TemplatesNotLoaded = 'templates_not_loaded';

    public function allowsSending(): bool
    {
        return $this === self::NotRequired || $this === self::Approved;
    }
}
