<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\OtpChannel;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\OtpCode;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\DecoyOtpChallenges;
use App\Services\Identity\SignInGuard;
use App\Services\Identity\SignInOtpChallenges;
use Illuminate\Support\Facades\Date;

final readonly class ResendSignInOtp
{
    public function __construct(
        private AuthSettings $settings,
        private SignInOtpChallenges $challenges,
        private DecoyOtpChallenges $decoys,
        private SignInGuard $guard,
        private IssueOtpChallenge $issueChallenge,
    ) {}

    public function handle(string $challengeId): OtpChallenge
    {
        $otp = $this->challenges->find($challengeId);

        if (! $otp instanceof OtpCode) {
            return $this->resendDecoy($challengeId);
        }

        if (! $otp->isOpen()) {
            throw OtpRejected::challengeInvalid();
        }

        $this->guard->assertNotLocked($otp->user);

        $availableAt = $otp->last_sent_at->addSeconds($this->settings->otpResendCooldownSeconds());

        if ($availableAt->isFuture()) {
            throw OtpRejected::cooldown((int) Date::now()->diffInSeconds($availableAt, true));
        }

        return $this->issueChallenge->handle(new OtpDelivery($otp->user, $otp->channel, $otp->purpose));
    }

    private function resendDecoy(string $challengeId): OtpChallenge
    {
        if (! $this->decoys->isDecoy($challengeId)) {
            throw OtpRejected::challengeInvalid();
        }

        $remaining = $this->decoys->cooldownRemainingSeconds($challengeId);

        if ($remaining > 0) {
            throw OtpRejected::cooldown($remaining);
        }

        return $this->decoys->issue(OtpChannel::Email);
    }
}
