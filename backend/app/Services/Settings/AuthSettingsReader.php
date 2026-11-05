<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\AuthSettingsView;
use App\Services\Identity\EntitySecondFactor;

final readonly class AuthSettingsReader
{
    public function __construct(private EntitySecondFactor $entitySecondFactor) {}

    public function read(): AuthSettingsView
    {
        return new AuthSettingsView($this->entitySecondFactor->isEnabled());
    }
}
