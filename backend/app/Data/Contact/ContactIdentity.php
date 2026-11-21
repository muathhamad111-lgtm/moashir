<?php

declare(strict_types=1);

namespace App\Data\Contact;

use App\Support\ValueObjects\EmailAddress;

final readonly class ContactIdentity
{
    public function __construct(
        public string $name,
        public EmailAddress $email,
        public ?string $organization,
    ) {}
}
