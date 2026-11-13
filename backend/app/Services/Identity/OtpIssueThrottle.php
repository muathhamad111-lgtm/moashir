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

    public function allows(User $user): bool
    {
        return $this->exhaustedWindow($user) === null;
    }

    public function hit(User $user): void
    {
        $exhausted = $this->exhaustedWindow($user);

        if ($exhausted !== null) {
            throw OtpRejected::cooldown($this->limiter->availableIn($exhausted));
        }

        foreach ($this->windowsFor($user) as $key => [, $decaySeconds]) {
            $this->limiter->hit($key, $decaySeconds);
        }
    }

    private function exhaustedWindow(User $user): ?string
    {
        foreach ($this->windowsFor($user) as $key => [$maxAttempts]) {
            if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
                return $key;
            }
        }

        return null;
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
