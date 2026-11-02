<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Contracts\Integrations\SmsAvailability;
use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\OtpChannel;
use App\Models\Identity\OtpCode;
use App\Notifications\Identity\OtpCodeNotification;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\SecretHasher;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\PhoneNumber;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final readonly class IssueOtpChallenge
{
    public function __construct(
        private AuthSettings $settings,
        private SecretHasher $hasher,
        private AuthenticationEventRecorder $events,
        private Request $request,
        private SmsAvailability $sms,
    ) {}

    public function handle(OtpDelivery $delivery): OtpChallenge
    {
        $channel = $this->effectiveChannel($delivery);
        $code = $this->generateCode();
        $now = CarbonImmutable::now();

        $otp = DB::transaction(function () use ($delivery, $channel, $code, $now): OtpCode {
            OtpCode::query()
                ->where('user_id', $delivery->user->id)
                ->where('purpose', $delivery->purpose)
                ->whereNull('consumed_at')
                ->whereNull('voided_at')
                ->update(['voided_at' => $now]);

            return OtpCode::query()->create([
                'challenge_id' => (string) Str::uuid(),
                'user_id' => $delivery->user->id,
                'channel' => $channel,
                'purpose' => $delivery->purpose,
                'code_hash' => $this->hasher->hash($code),
                'destination_masked' => $this->maskedDestination($delivery, $channel),
                'expires_at' => $now->addSeconds($this->settings->otpExpirySeconds()),
                'last_sent_at' => $now,
                'ip_address' => $this->request->ip(),
            ]);
        });

        $this->send($delivery, $channel, $code);
        $this->events->forUser(AuthEventType::OtpSent, $delivery->user, ['channel' => $channel->value, 'purpose' => $delivery->purpose->value]);

        return new OtpChallenge(
            $otp->challenge_id,
            $channel,
            $this->settings->otpLength(),
            $otp->expires_at->toImmutable(),
            $now->addSeconds($this->settings->otpResendCooldownSeconds()),
        );
    }

    private function effectiveChannel(OtpDelivery $delivery): OtpChannel
    {
        $canUseSms = ! $delivery->recipientOverride instanceof EmailAddress && $delivery->user->phone !== null && $this->settings->isSmsOtpEnabled();

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
