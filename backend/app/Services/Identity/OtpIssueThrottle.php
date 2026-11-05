<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\User;
use Illuminate\Cache\RateLimiter;

final readonly class OtpIssueThrottle
{
    private const int TEN_MINUTES = 600;

    private const int ONE_DAY = 86400;

    public function __construct(private RateLimiter $limiter, private AuthSettings $settings) {}

    public function hit(User $user): void
    {
        $windows = $this->windowsFor($user);

        foreach ($windows as $key => [$maxAttempts]) {
            if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
                throw OtpRejected::cooldown($this->limiter->availableIn($key));
            }
        }

        foreach ($windows as $key => [, $decaySeconds]) {
            $this->limiter->hit($key, $decaySeconds);
        }
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    private function windowsFor(User $user): array
    {
        return [
            'otp-issue-user:'.$user->id => [$this->settings->otpPerUserPerTenMinutes(), self::TEN_MINUTES],
            'otp-issue-user-day:'.$user->id => [$this->settings->otpPerUserPerDay(), self::ONE_DAY],
        ];
    }
}
