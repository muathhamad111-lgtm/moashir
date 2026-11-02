<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\OtpCode;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\DecoyOtpChallenges;
use Illuminate\Support\Facades\Date;

final readonly class ResendSignInOtp
{
    public function __construct(
        private AuthSettings $settings,
        private DecoyOtpChallenges $decoys,
        private IssueOtpChallenge $issueChallenge,
    ) {}

    public function handle(string $challengeId, ?OtpChannel $channel): OtpChallenge
    {
        $otp = OtpCode::query()->where('challenge_id', $challengeId)->where('purpose', OtpPurpose::Login)->first();

        if ($otp === null) {
            return $this->resendDecoy($challengeId, $channel ?? OtpChannel::Email);
        }

        if (! $otp->isOpen()) {
            throw OtpRejected::challengeInvalid();
        }

        $availableAt = $otp->last_sent_at->addSeconds($this->settings->otpResendCooldownSeconds());

        if ($availableAt->isFuture()) {
            throw OtpRejected::cooldown((int) Date::now()->diffInSeconds($availableAt, true));
        }

        return $this->issueChallenge->handle(new OtpDelivery($otp->user, $channel ?? $otp->channel, OtpPurpose::Login));
    }

    private function resendDecoy(string $challengeId, OtpChannel $channel): OtpChallenge
    {
        if (! $this->decoys->isDecoy($challengeId)) {
            throw OtpRejected::challengeInvalid();
        }

        $remaining = $this->decoys->cooldownRemainingSeconds($challengeId);

        if ($remaining > 0) {
            throw OtpRejected::cooldown($remaining);
        }

        return $this->decoys->issue($channel);
    }
}
