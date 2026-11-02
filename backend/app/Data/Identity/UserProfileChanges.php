<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\PhoneNumber;

final readonly class UserProfileChanges
{
    /**
     * @param  list<string>|null  $roles
     */
    public function __construct(
        public ?string $name = null,
        public ?EmailAddress $email = null,
        public ?PhoneNumber $phone = null,
        public ?array $roles = null,
    ) {}
}
