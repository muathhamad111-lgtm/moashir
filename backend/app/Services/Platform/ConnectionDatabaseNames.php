<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\ConfigurationUrlParser;

final readonly class ConnectionDatabaseNames
{
    public function __construct(private Repository $config, private ConfigurationUrlParser $urlParser) {}

    public function of(?string $connection): ?string
    {
        $settings = $this->config->get('database.connections.'.($connection ?? $this->config->string('database.default')));

        if (! is_array($settings)) {
            return null;
        }

        return $this->databaseFromUrl($settings['url'] ?? null) ?? $this->nonEmptyString($settings['database'] ?? null);
    }

    /**
     * @return list<?string>
     */
    public function ofEveryConnection(): array
    {
        $connections = $this->config->get('database.connections');

        return array_map(
            fn (int|string $connection): ?string => $this->of((string) $connection),
            array_keys(is_array($connections) ? $connections : []),
        );
    }

    private function databaseFromUrl(mixed $url): ?string
    {
        $url = $this->nonEmptyString($url);

        return $url === null ? null : $this->nonEmptyString($this->urlParser->parseConfiguration($url)['database'] ?? null);
    }

    private function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
