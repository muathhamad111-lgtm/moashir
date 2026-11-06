<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\OtpChannel;
use App\Models\Identity\User;
use App\Support\ValueObjects\SignInIdentifier;

final readonly class SignInCodeTarget
{
    public function __construct(
        public SignInIdentifier $identifier,
        public ?User $account,
        public OtpChannel $channel,
    ) {}
}
