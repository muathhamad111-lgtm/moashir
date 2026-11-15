<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Identity\OtpChallenge;
use App\Enums\Identity\OtpChannel;
use App\Notifications\Identity\OtpCodeNotification;
use App\Services\Entities\RegistrationEmailCodes;
use App\Services\Entities\RegistrationEmailIndex;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\OtpCodeGenerator;
use App\Services\Identity\OtpIssueThrottle;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Support\Facades\Notification;

/**
 * E-mails a code to the official address typed on the registration form. The answer never reveals
 * whether the address already belongs to an account: the owner of a taken address receives a code
 * too, and only learns that the address is taken after proving it.
 */
final readonly class SendRegistrationEmailCode
{
    public function __construct(
        private AuthSettings $settings,
        private OtpIssueThrottle $throttle,
        private OtpCodeGenerator $generator,
        private RegistrationEmailCodes $codes,
        private RegistrationEmailIndex $index,
    ) {}

    public function handle(EmailAddress $email): OtpChallenge
    {
        $this->throttle->hitRegistrationAddress($this->index->of($email));

        $code = $this->generator->generate();
        $verification = $this->codes->open($email, $code);

        Notification::route('mail', $email->value)->notify(
            new OtpCodeNotification($code, OtpChannel::Email, intdiv($this->settings->otpExpirySeconds(), 60))->locale(app()->getLocale()),
        );

        return new OtpChallenge(
            $verification->challenge_id,
            OtpChannel::Email,
            $this->settings->otpLength(),
            $verification->expires_at->toImmutable(),
            $verification->last_sent_at->toImmutable()->addSeconds($this->settings->otpResendCooldownSeconds()),
        );
    }
}
