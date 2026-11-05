<?php

declare(strict_types=1);

namespace App\Notifications\Identity;

use App\Enums\Identity\OtpChannel;
use App\Enums\Integrations\SmsPurpose;
use App\Enums\Integrations\SmsTemplateKey;
use App\Enums\Notifications\MailReason;
use App\Models\Identity\User;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Contracts\DeliversSms;
use App\Notifications\Contracts\FallsBackToMail;
use App\Notifications\Messages\BrandedMailMessage;
use App\Notifications\QueuedNotification;
use App\Services\Integrations\SmsTemplateRenderer;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\AnonymousNotifiable;

final class OtpCodeNotification extends QueuedNotification implements DeliversSms, FallsBackToMail, ShouldBeEncrypted
{
    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    private readonly CarbonImmutable $expiresAt;

    public function __construct(
        private readonly string $code,
        private readonly OtpChannel $channel,
        private readonly int $validMinutes,
    ) {
        parent::__construct();
        $this->expiresAt = CarbonImmutable::now()->addMinutes($validMinutes);
    }

    /**
     * @return list<string>
     */
    public function via(User|AnonymousNotifiable $notifiable): array
    {
        return $this->channel === OtpChannel::Sms && $notifiable instanceof User ? [SmsChannel::class] : ['mail'];
    }

    public function toMail(User|AnonymousNotifiable $notifiable): BrandedMailMessage
    {
        return BrandedMailMessage::make($notifiable, $notifiable instanceof User ? MailReason::Account : MailReason::AddressVerification)
            ->subject(__('notifications.otp.subject'))
            ->line(__('notifications.otp.line'))
            ->code($this->code)
            ->line(__('notifications.otp.expiry', ['duration' => $this->validity()]))
            ->line(__('notifications.otp.never_share'))
            ->line(__('notifications.otp.ignore'));
    }

    public function toSms(User $notifiable): string
    {
        return app(SmsTemplateRenderer::class)->render(SmsTemplateKey::OtpCode, app()->getLocale(), [
            'code' => $this->code,
            'validity' => $this->validity(),
            'minutes' => (string) $this->validMinutes,
        ]);
    }

    public function smsPurpose(): SmsPurpose
    {
        return SmsPurpose::Otp;
    }

    public function mailFallback(): self
    {
        $fallback = new self($this->code, OtpChannel::Email, $this->validMinutes);
        $fallback->locale = $this->locale;

        return $fallback;
    }

    public function retryUntil(): DateTimeInterface
    {
        return $this->expiresAt;
    }

    private function validity(): string
    {
        return trans_choice('quantities.minutes', $this->validMinutes);
    }
}
