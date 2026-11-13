<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Data\Identity\SignInCodeTarget;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Enums\Identity\SignInCodeVerdict;
use App\Exceptions\Identity\OtpRejected;
use App\Models\Identity\User;
use App\Services\Identity\SignInAccountResolver;
use App\Services\Identity\SignInCodeChannelResolver;
use App\Services\Identity\SignInCodeDecoy;
use App\Services\Identity\SignInCodeEligibility;
use App\Support\ValueObjects\SignInIdentifier;

final readonly class RequestSignInCode
{
    public function __construct(
        private SignInAccountResolver $accounts,
        private SignInCodeChannelResolver $channels,
        private SignInCodeEligibility $eligibility,
        private IssueSignInCode $issueCode,
        private SignInCodeDecoy $decoy,
    ) {}

    public function handle(SignInIdentifier $identifier): OtpChallenge
    {
        $target = new SignInCodeTarget($identifier, $this->accounts->resolve($identifier), $this->channels->forIdentifier($identifier));
        $verdict = $this->eligibility->of($target->account);

        if (! $target->account instanceof User || ! $verdict->sendsCode()) {
            return $this->decoy->answer($target, $verdict);
        }

        return $this->send($target, $this->deliveryFor($target->account, $target->channel, $verdict));
    }

    private function deliveryFor(User $account, OtpChannel $channel, SignInCodeVerdict $verdict): OtpDelivery
    {
        return $verdict === SignInCodeVerdict::RegistrationCode
            ? new OtpDelivery($account, OtpChannel::Email, OtpPurpose::Login)
            : new OtpDelivery($account, $channel, OtpPurpose::SignInCode);
    }

    private function send(SignInCodeTarget $target, OtpDelivery $delivery): OtpChallenge
    {
        try {
            return $this->issueCode->handle($delivery);
        } catch (OtpRejected $rejection) {
            if (! $rejection->isCooldown()) {
                throw $rejection;
            }

            return $this->decoy->answer($target, SignInCodeVerdict::Throttled);
        }
    }
}
