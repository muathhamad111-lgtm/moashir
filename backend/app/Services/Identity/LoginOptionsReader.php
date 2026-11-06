<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Data\Identity\LoginOptions;
use App\Enums\Identity\SignInIdentifierType;

final readonly class LoginOptionsReader
{
    public function __construct(private AuthSettings $settings, private SignInCodeChannels $codeChannels) {}

    public function read(): LoginOptions
    {
        return new LoginOptions(
            SignInIdentifierType::cases(),
            $this->codeChannels->usable(),
            $this->settings->otpLength(),
            $this->settings->otpExpirySeconds(),
            $this->settings->otpResendCooldownSeconds(),
        );
    }
}
