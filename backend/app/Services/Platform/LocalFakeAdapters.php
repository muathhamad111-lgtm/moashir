<?php

declare(strict_types=1);

namespace App\Services\Platform;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Psr\Log\LoggerInterface;

final readonly class LocalFakeAdapters
{
    public const string FAKE = 'fake';

    private const array ALLOWED_ENVIRONMENTS = ['local', 'testing'];

    public function __construct(private Application $app, private Repository $config, private LoggerInterface $logger) {}

    public function allows(string $configKey): bool
    {
        if ($this->config->get($configKey) !== self::FAKE) {
            return false;
        }

        if ($this->app->environment(self::ALLOWED_ENVIRONMENTS)) {
            return true;
        }

        $this->logger->critical('platform.fake_adapter_refused', [
            'setting' => $configKey,
            'environment' => $this->app->environment(),
        ]);

        return false;
    }
}
