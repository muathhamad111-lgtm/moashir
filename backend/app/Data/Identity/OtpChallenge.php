<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\OtpChannel;
use Carbon\CarbonImmutable;

final readonly class OtpChallenge
{
    public function __construct(
        public string $challengeId,
        public OtpChannel $channel,
        public int $length,
        public CarbonImmutable $expiresAt,
        public CarbonImmutable $resendAvailableAt,
    ) {}
}
