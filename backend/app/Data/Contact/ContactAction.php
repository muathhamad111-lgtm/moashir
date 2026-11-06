<?php

declare(strict_types=1);

namespace App\Data\Contact;

final readonly class ContactAction
{
    public function __construct(public string $body, public ?string $reason) {}
}
