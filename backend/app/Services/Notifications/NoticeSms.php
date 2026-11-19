<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Notifications\NoticeText;
use App\Enums\Notifications\NotificationChannel;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Support\Facades\Lang;

/**
 * Decides whether a notice also goes out as a text message: the System Administrator switched SMS on for the
 * type, wrote its SMS text, the recipient has a phone, and the text is allowed by the SMS provider.
 */
final readonly class NoticeSms
{
    public function __construct(private SystemNotificationChannels $channels, private SmsTextApprovals $approvals) {}

    public function isSendable(NotificationCode $code, ?NoticeText $text, User $recipient): bool
    {
        if (! $text instanceof NoticeText || $recipient->routeNotificationForSms() === null || ! $this->channels->isSwitchedOn($code, NotificationChannel::Sms)) {
            return false;
        }

        $template = Lang::get($text->key.'.sms', [], $recipient->preferredLocale(), false);

        return is_string($template) && $text->sms() instanceof LocalizedText && $this->approvals->of($template)->allowsSending();
    }
}
