<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\SignInIdentifierType;

final readonly class LoginOptions
{
    /**
     * @param  list<SignInIdentifierType>  $identifiers
     * @param  list<OtpChannel>  $codeChannels
     */
    public function __construct(
        public array $identifiers,
        public array $codeChannels,
        public int $otpLength,
        public int $otpExpirySeconds,
        public int $resendCooldownSeconds,
    ) {}
}
