<?php

declare(strict_types=1);

namespace App\Services\Platform\Readiness;

use App\Contracts\Platform\ReadinessCheck;
use App\Enums\Platform\ReadinessCheckStatus;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Throwable;

final readonly class RedisReadiness implements ReadinessCheck
{
    private const string REDIS = 'redis';

    public function __construct(private RedisFactory $redis, private Repository $config, private ReadinessFailureLog $failures) {}

    public function name(): string
    {
        return 'redis';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function status(): ReadinessCheckStatus
    {
        if ($this->config->get('cache.default') !== self::REDIS && $this->config->get('queue.default') !== self::REDIS) {
            return ReadinessCheckStatus::Skipped;
        }

        try {
            $this->redis->connection()->command('ping');
        } catch (Throwable $failure) {
            return $this->failures->record($this->name(), $failure);
        }

        return ReadinessCheckStatus::Ok;
    }
}
