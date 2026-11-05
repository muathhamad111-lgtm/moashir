<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Contracts\Integrations\SmsAvailability;
use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\OtpChannel;
use App\Notifications\Identity\OtpCodeNotification;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\OtpCodeStore;
use App\Services\Identity\OtpIssueThrottle;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\Notification;

final readonly class IssueOtpChallenge
{
    public function __construct(
        private AuthSettings $settings,
        private OtpIssueThrottle $throttle,
        private OtpCodeStore $store,
        private AuthenticationEventRecorder $events,
        private SmsAvailability $sms,
    ) {}

    public function handle(OtpDelivery $delivery): OtpChallenge
    {
        $this->throttle->hit($delivery->user);

        $channel = $this->effectiveChannel($delivery);
        $code = $this->generateCode();
        $otp = $this->store->open($delivery, $channel, $code, $this->maskedDestination($delivery, $channel));

        $this->send($delivery, $channel, $code);
        $this->events->forUser(AuthEventType::OtpSent, $delivery->user, ['channel' => $channel->value, 'purpose' => $delivery->purpose->value]);

        return new OtpChallenge(
            $otp->challenge_id,
            $channel,
            $this->settings->otpLength(),
            $otp->expires_at->toImmutable(),
            $otp->last_sent_at->toImmutable()->addSeconds($this->settings->otpResendCooldownSeconds()),
        );
    }

    private function effectiveChannel(OtpDelivery $delivery): OtpChannel
    {
        $canUseSms = ! $delivery->recipientOverride instanceof EmailAddress && $delivery->user->phone !== null;

        return $delivery->channel === OtpChannel::Sms && $canUseSms && $this->sms->isAvailable() ? OtpChannel::Sms : OtpChannel::Email;
    }

    private function generateCode(): string
    {
        $length = $this->settings->otpLength();

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function maskedDestination(OtpDelivery $delivery, OtpChannel $channel): string
    {
        if ($channel === OtpChannel::Sms && $delivery->user->phone !== null) {
            return new PhoneNumber($delivery->user->phone)->masked();
        }

        return ($delivery->recipientOverride ?? new EmailAddress($delivery->user->email))->masked();
    }

    private function send(OtpDelivery $delivery, OtpChannel $channel, string $code): void
    {
        $notification = new OtpCodeNotification($code, $channel, intdiv($this->settings->otpExpirySeconds(), 60));

        if ($delivery->recipientOverride instanceof EmailAddress) {
            Notification::route('mail', $delivery->recipientOverride->value)
                ->notify($notification->locale($delivery->user->preferredLocale()));

            return;
        }

        $delivery->user->notify($notification);
    }
}
