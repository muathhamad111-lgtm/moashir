<?php

declare(strict_types=1);

namespace App\Actions\Identity;

use App\Contracts\Integrations\SmsAvailability;
use App\Data\Identity\OtpChallenge;
use App\Data\Identity\OtpDelivery;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Exceptions\Identity\PhoneVerificationRejected;
use App\Models\Identity\User;

final readonly class StartPhoneVerification
{
    public function __construct(private SmsAvailability $sms, private IssueOtpChallenge $issueChallenge) {}

    public function handle(User $user): OtpChallenge
    {
        if ($user->phone === null) {
            throw PhoneVerificationRejected::missing();
        }

        if ($user->phone_verified_at !== null) {
            throw PhoneVerificationRejected::alreadyVerified();
        }

        if (! $this->sms->isAvailable()) {
            throw PhoneVerificationRejected::smsUnavailable();
        }

        return $this->issueChallenge->handle(new OtpDelivery($user, OtpChannel::Sms, OtpPurpose::VerifyPhone));
    }
}
