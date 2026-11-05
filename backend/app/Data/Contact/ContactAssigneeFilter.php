<?php

declare(strict_types=1);

namespace App\Data\Contact;

final readonly class ContactAssigneeFilter
{
    public function __construct(public ?int $userId) {}

    public static function unassigned(): self
    {
        return new self(null);
    }

    public function isUnassigned(): bool
    {
        return $this->userId === null;
    }
}
