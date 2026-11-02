<?php

declare(strict_types=1);

namespace App\Exceptions\Integrations;

use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\ProviderErrorKind;
use RuntimeException;

abstract class SmsDeliveryFailed extends RuntimeException
{
    final public function __construct(public readonly ProviderErrorKind $kind, public readonly ?IntegrationProvider $provider)
    {
        parent::__construct(sprintf('The SMS could not be delivered: %s.', $kind->value));
    }

    public static function from(ProviderCallFailed $failure): self
    {
        return $failure->isTransient()
            ? new SmsTransientFailure($failure->kind, $failure->provider)
            : new SmsPermanentFailure($failure->kind, $failure->provider);
    }

    public static function notConfigured(): SmsPermanentFailure
    {
        return new SmsPermanentFailure(ProviderErrorKind::Misconfigured, null);
    }
}
