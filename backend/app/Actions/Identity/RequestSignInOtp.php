<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\AuthEventType;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Exceptions\Identity\AuthenticationRejected;
use App\Models\Identity\User;
use App\Services\Identity\AuthenticationEventRecorder;
use App\Services\Identity\AuthSettings;
use App\Services\Identity\DecoyOtpChallenges;
use App\Services\Identity\SignInGuard;
use App\Support\ValueObjects\EmailAddress;

final readonly class RequestSignInOtp
{
    public function __construct(
        private AuthSettings $settings,
        private SignInGuard $guard,
        private DecoyOtpChallenges $decoys,
        private AuthenticationEventRecorder $events,
        private IssueOtpChallenge $issueChallenge,
    ) {}

    public function handle(EmailAddress $email, OtpChannel $channel): OtpChallenge
    {
        $this->assertChannelEnabled($channel);

        $user = User::query()->withEmail($email)->first();

        if ($user === null || ! $this->guard->canReceiveOtp($user)) {
            $this->events->forUnknownEmail(AuthEventType::OtpFailed, $email->value);

            return $this->decoys->issue($channel);
        }

        return $this->issueChallenge->handle(new OtpDelivery($user, $channel, OtpPurpose::Login));
    }

    private function assertChannelEnabled(OtpChannel $channel): void
    {
        $enabled = match ($channel) {
            OtpChannel::Email => $this->settings->isEmailOtpEnabled(),
            OtpChannel::Sms => $this->settings->isSmsOtpEnabled(),
        };

        if (! $enabled) {
            throw AuthenticationRejected::modeDisabled();
        }
    }
}
