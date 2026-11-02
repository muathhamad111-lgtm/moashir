<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum IntegrationMode: string
{
    case Test = 'test';
    case Live = 'live';

    public function isTest(): bool
    {
        return $this === self::Test;
    }

    public static function fromLiveFlag(bool $isLive): self
    {
        return $isLive ? self::Live : self::Test;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
