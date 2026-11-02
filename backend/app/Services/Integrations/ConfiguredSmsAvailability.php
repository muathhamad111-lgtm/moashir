<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\SmsAvailability;
use App\Data\Integrations\SmsDriverSelection;
use App\Enums\Integrations\IntegrationKind;

final readonly class ConfiguredSmsAvailability implements SmsAvailability
{
    public function __construct(private SmsDriverSelection $driver, private IntegrationCredentialsResolver $resolver) {}

    public function isAvailable(): bool
    {
        if ($this->driver->usesFakeGateway) {
            return true;
        }

        return $this->resolver->resolve(IntegrationKind::Sms)->isConfigured();
    }
}
