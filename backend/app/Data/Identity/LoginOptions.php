<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\SignInIdentifierType;

final readonly class LoginOptions
{
    /**
     * @param  list<SignInIdentifierType>  $identifiers
     */
    public function __construct(
        public array $identifiers,
        public bool $entitySecondFactorEnabled,
        public int $otpLength,
        public int $otpExpirySeconds,
        public int $resendCooldownSeconds,
    ) {}
}
