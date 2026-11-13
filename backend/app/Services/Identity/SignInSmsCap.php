<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\OtpChallenge;
use App\Enums\Identity\OtpChannel;
use Illuminate\Cache\RateLimiter;

final readonly class SignInSmsCap
{
    private const string KEY = 'sign-in-sms-hour';

    private const int ONE_HOUR = 3600;

    public function __construct(private RateLimiter $limiter, private AuthSettings $settings) {}

    public function hasRoom(): bool
    {
        return ! $this->limiter->tooManyAttempts(self::KEY, $this->settings->signInSmsPerHour());
    }

    public function recordSent(OtpChallenge $challenge): void
    {
        if ($challenge->channel === OtpChannel::Sms) {
            $this->limiter->hit(self::KEY, self::ONE_HOUR);
        }
    }
}
