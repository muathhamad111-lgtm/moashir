<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\OtpPurpose;
use App\Enums\Identity\PendingAuthenticationStage;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\OtpCode;

final readonly class SignInOtpChallenges
{
    public function __construct(private PendingAuthentication $pending) {}

    public function find(string $challengeId): ?OtpCode
    {
        $challenge = OtpCode::query()
            ->where('challenge_id', $challengeId)
            ->whereIn('purpose', OtpPurpose::signIn())
            ->first();

        if ($challenge?->purpose === OtpPurpose::LoginSecondFactor && ! $this->pending->holds($challenge->user, PendingAuthenticationStage::EmailSecondFactor)) {
            throw OtpRejected::challengeInvalid();
        }

        return $challenge;
    }

    public function release(OtpCode $challenge): void
    {
        if ($challenge->purpose === OtpPurpose::LoginSecondFactor) {
            $this->pending->clear();
        }
    }
}
