<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\OtpChallenge;
use App\Enums\Identity\OtpChannel;
use App\Support\ScalarValue;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Str;

final readonly class DecoyOtpChallenges
{
    private const string PREFIX = 'otp-decoy:';

    private const string SENT_PREFIX = 'otp-decoy-sent:';

    public function __construct(private Repository $cache, private AuthSettings $settings) {}

    public function issue(OtpChannel $channel): OtpChallenge
    {
        $challengeId = (string) Str::uuid();
        $now = CarbonImmutable::now();
        $expiresAt = $now->addSeconds($this->settings->otpExpirySeconds());

        $this->cache->put(self::PREFIX.$challengeId, 0, $expiresAt);
        $this->cache->put(self::SENT_PREFIX.$challengeId, $now->getTimestamp(), $expiresAt);

        return new OtpChallenge(
            $challengeId,
            $channel,
            $this->settings->otpLength(),
            $expiresAt,
            $now->addSeconds($this->settings->otpResendCooldownSeconds()),
        );
    }

    public function isDecoy(string $challengeId): bool
    {
        return $this->cache->has(self::PREFIX.$challengeId);
    }

    public function cooldownRemainingSeconds(string $challengeId): int
    {
        $sentAt = ScalarValue::int($this->cache->get(self::SENT_PREFIX.$challengeId));

        return max(0, $sentAt + $this->settings->otpResendCooldownSeconds() - CarbonImmutable::now()->getTimestamp());
    }

    public function recordAttempt(string $challengeId): int
    {
        return (int) $this->cache->increment(self::PREFIX.$challengeId);
    }
}
