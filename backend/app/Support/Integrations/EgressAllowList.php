<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use App\Enums\Integrations\OpenAiRegion;
use InvalidArgumentException;

final class EgressAllowList
{
    public const string MOYASAR = 'api.moyasar.com';

    public const string TAQNYAT = 'api.taqnyat.sa';

    public const string MSEGAT = 'www.msegat.com';

    public const string ANTHROPIC = 'api.anthropic.com';

    /**
     * @return list<string>
     */
    public static function hosts(): array
    {
        return [
            self::MOYASAR,
            self::TAQNYAT,
            self::MSEGAT,
            ...array_map(static fn (OpenAiRegion $region): string => $region->host(), OpenAiRegion::cases()),
            self::ANTHROPIC,
        ];
    }

    public static function httpsUrl(string $host): string
    {
        self::assertAllowed($host);

        return 'https://'.$host;
    }

    public static function assertAllowed(string $host): void
    {
        if (! in_array(mb_strtolower($host), self::hosts(), true)) {
            throw new InvalidArgumentException('The host is not on the integration egress allow-list.');
        }
    }
}
