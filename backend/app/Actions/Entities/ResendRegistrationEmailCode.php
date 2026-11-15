<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Identity\OtpChallenge;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\RegistrationEmailVerification;
use App\Services\Entities\RegistrationEmailCodes;
use App\Services\Identity\AuthSettings;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Support\Facades\Date;

final readonly class ResendRegistrationEmailCode
{
    public function __construct(
        private RegistrationEmailCodes $codes,
        private AuthSettings $settings,
        private SendRegistrationEmailCode $sendCode,
    ) {}

    public function handle(string $challengeId): OtpChallenge
    {
        $verification = $this->codes->findOpen($challengeId);

        if (! $verification instanceof RegistrationEmailVerification) {
            throw OtpRejected::challengeInvalid();
        }

        $availableAt = $verification->last_sent_at->copy()->addSeconds($this->settings->otpResendCooldownSeconds());

        if ($availableAt->isFuture()) {
            throw OtpRejected::cooldown((int) Date::now()->diffInSeconds($availableAt, true));
        }

        return $this->sendCode->handle(new EmailAddress($verification->email));
    }
}
