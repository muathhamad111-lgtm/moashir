<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Contracts\SmsGateway;
use App\Data\Integrations\SmsMessage;
use App\Enums\Notifications\NotificationCode;
use App\Exceptions\Integrations\SmsPermanentFailure;
use App\Models\Identity\User;
use App\Notifications\Contracts\DeliversSms;
use App\Notifications\Contracts\FallsBackToMail;
use App\Services\Integrations\IntegrationNotifier;
use App\Support\ValueObjects\PhoneNumber;

final readonly class SmsChannel
{
    public function __construct(private SmsGateway $gateway, private IntegrationNotifier $notifier) {}

    public function send(User $notifiable, DeliversSms $notification): void
    {
        $phone = $notifiable->routeNotificationForSms();

        if ($phone === null) {
            return;
        }

        try {
            $this->gateway->send(new SmsMessage(new PhoneNumber($phone), $notification->toSms($notifiable), $notification->smsPurpose()));
        } catch (SmsPermanentFailure $failure) {
            $provider = $failure->provider->value ?? 'none';
            $this->notifier->notifyOnce(NotificationCode::SmsSendFailed, $provider.':'.$failure->kind->value, [
                'provider' => $provider,
                'error' => $failure->kind->value,
            ]);

            if ($notification instanceof FallsBackToMail) {
                $notifiable->notifyNow($notification->mailFallback(), ['mail']);
            }
        }
    }
}
