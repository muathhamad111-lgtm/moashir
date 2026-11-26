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

    private const string REGISTRATION_ADDRESS_PREFIX = 'registration:';

    public function __construct(private RateLimiter $limiter, private AuthSettings $settings) {}

    public function allows(User $user): bool
    {
        return $this->exhaustedWindow((string) $user->id) === null;
    }

    public function hit(User $user): void
    {
        $this->hitSubject((string) $user->id);
    }

    /**
     * Applies the per-account limits to an address that has no account yet (registration e-mail check).
     */
    public function hitRegistrationAddress(string $emailIndex): void
    {
        $this->hitSubject(self::REGISTRATION_ADDRESS_PREFIX.$emailIndex);
    }

    private function hitSubject(string $subject): void
    {
        $exhausted = $this->exhaustedWindow($subject);

        if ($exhausted !== null) {
            throw OtpRejected::cooldown($this->limiter->availableIn($exhausted));
        }

        foreach ($this->windowsFor($subject) as $key => [, $decaySeconds]) {
            $this->limiter->hit($key, $decaySeconds);
        }
    }

    private function exhaustedWindow(string $subject): ?string
    {
        foreach ($this->windowsFor($subject) as $key => [$maxAttempts]) {
            if ($this->limiter->tooManyAttempts($key, $maxAttempts)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{0: int, 1: int}>
     */
    private function windowsFor(string $subject): array
    {
        return [
            'otp-issue-user:'.$subject => [$this->settings->otpPerUserPerTenMinutes(), self::TEN_MINUTES],
            'otp-issue-user-day:'.$subject => [$this->settings->otpPerUserPerDay(), self::ONE_DAY],
        ];
    }
}
