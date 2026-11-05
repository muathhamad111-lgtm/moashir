<?php

declare(strict_types=1);

namespace App\Data\Settings;

final readonly class AuthSettingsUpdate
{
    public function __construct(public ?bool $entitySecondFactorEnabled, public ?string $reason) {}

    public function isEmpty(): bool
    {
        return $this->entitySecondFactorEnabled === null;
    }
}
