<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Models\Identity\User;
use Illuminate\Support\Carbon;

final readonly class SessionTimeouts
{
    private const int SECONDS_PER_MINUTE = 60;

    private const int SECONDS_PER_HOUR = 3600;

    public function __construct(private AuthSettings $settings) {}

    public function idleMinutesFor(User $user): int
    {
        return $this->settings->idleMinutes($user->sessionGroup());
    }

    public function hasExpired(User $user, int $signedInAt, int $lastSeenAt, Carbon $now): bool
    {
        $idleLimit = $this->idleMinutesFor($user) * self::SECONDS_PER_MINUTE;
        $absoluteLimit = $this->settings->absoluteSessionHours() * self::SECONDS_PER_HOUR;
        $timestamp = $now->getTimestamp();

        return $timestamp - $lastSeenAt > $idleLimit || $timestamp - $signedInAt > $absoluteLimit;
    }

    public function absoluteExpiry(int $signedInAt): Carbon
    {
        return Carbon::createFromTimestamp($signedInAt + $this->settings->absoluteSessionHours() * self::SECONDS_PER_HOUR);
    }
}
