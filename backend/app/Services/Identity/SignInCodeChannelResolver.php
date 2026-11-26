<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\SignInIdentifierType;
use App\Support\ValueObjects\SignInIdentifier;

final readonly class SignInCodeChannelResolver
{
    public function __construct(private SignInCodeChannels $channels, private SignInSmsCap $smsCap) {}

    public function forIdentifier(SignInIdentifier $identifier): OtpChannel
    {
        return $this->available($identifier->type === SignInIdentifierType::Mobile ? OtpChannel::Sms : OtpChannel::Email);
    }

    public function forResend(OtpChannel $previous): OtpChannel
    {
        return $this->available($previous);
    }

    private function available(OtpChannel $wanted): OtpChannel
    {
        return $wanted === OtpChannel::Sms && $this->channels->canSendSms() && $this->smsCap->hasRoom()
            ? OtpChannel::Sms
            : OtpChannel::Email;
    }
}
