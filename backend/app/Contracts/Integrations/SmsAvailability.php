<?php

declare(strict_types=1);

namespace App\Contracts\Integrations;

interface SmsAvailability
{
    public function isAvailable(): bool;
}
