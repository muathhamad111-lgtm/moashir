<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\OtpPurpose;
use App\Models\Identity\User;
use App\Support\ValueObjects\EmailAddress;

final readonly class OtpDelivery
{
    public function __construct(
        public User $user,
        public OtpChannel $channel,
        public OtpPurpose $purpose,
        public ?EmailAddress $recipientOverride = null,
    ) {}
}
