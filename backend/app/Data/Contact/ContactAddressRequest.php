<?php

declare(strict_types=1);

namespace App\Data\Contact;

use App\Support\ValueObjects\EmailAddress;

final readonly class ContactAddressRequest
{
    public function __construct(public EmailAddress $email, public string $reason) {}
}
