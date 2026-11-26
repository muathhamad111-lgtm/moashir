<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\OtpCode;
use App\Services\Identity\DecoyOtpChallenges;
use App\Services\Identity\SignInCodeChannelResolver;
use App\Services\Identity\SignInOtpChallenges;

final readonly class ResendSignInOtp
{
    public function __construct(
        private SignInOtpChallenges $challenges,
        private DecoyOtpChallenges $decoys,
        private SignInCodeChannelResolver $channels,
        private IssueSignInCode $issueCode,
    ) {}

    public function handle(string $challengeId): OtpChallenge
    {
        $otp = $this->challenges->find($challengeId);

        if (! $otp instanceof OtpCode) {
            return $this->resendDecoy($challengeId);
        }

        $this->challenges->assertResendable($otp);

        return $this->issueCode->handle(new OtpDelivery($otp->user, $this->channels->forResend($otp->channel), $otp->purpose));
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

        return $this->decoys->issue($this->channels->forResend($this->decoys->channelOf($challengeId)));
    }
}
