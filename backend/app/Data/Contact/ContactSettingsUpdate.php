<?php

declare(strict_types=1);

namespace App\Data\Contact;

final readonly class ContactSettingsUpdate
{
    public function __construct(public int $retentionMonths, public ?string $reason) {}
}
