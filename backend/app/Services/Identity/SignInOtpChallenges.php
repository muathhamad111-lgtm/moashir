<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\OtpPurpose;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\OtpCode;
use Illuminate\Support\Facades\Date;

final readonly class SignInOtpChallenges
{
    public function __construct(private AuthSettings $settings, private SignInGuard $guard) {}

    public function find(string $challengeId): ?OtpCode
    {
        $challenge = OtpCode::query()
            ->where('challenge_id', $challengeId)
            ->whereIn('purpose', OtpPurpose::signIn())
            ->first();

        if ($challenge?->purpose === OtpPurpose::SignInCode && ! $challenge->user->mayUseSignInCode()) {
            throw OtpRejected::challengeInvalid();
        }

        return $challenge;
    }

    public function assertResendable(OtpCode $challenge): void
    {
        if (! $challenge->isOpen()) {
            throw OtpRejected::challengeInvalid();
        }

        $this->guard->assertNotLocked($challenge->user);

        $availableAt = $challenge->last_sent_at->addSeconds($this->settings->otpResendCooldownSeconds());

        if ($availableAt->isFuture()) {
            throw OtpRejected::cooldown((int) Date::now()->diffInSeconds($availableAt, true));
        }
    }
}
