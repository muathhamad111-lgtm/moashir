<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum SmsGatewayStatus: string
{
    case Unavailable = 'unavailable';
    case Test = 'test';
    case Live = 'live';

    public static function fromMode(IntegrationMode $mode): self
    {
        return $mode->isTest() ? self::Test : self::Live;
    }
}
