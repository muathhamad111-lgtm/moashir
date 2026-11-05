<?php

declare(strict_types=1);

namespace App\Data\Settings;

final readonly class AuthSettingsView
{
    public function __construct(public bool $entitySecondFactorEnabled) {}
}
