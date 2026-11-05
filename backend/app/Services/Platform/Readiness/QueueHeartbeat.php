<?php

declare(strict_types=1);

namespace App\Services\Platform\Readiness;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\Date;

final readonly class QueueHeartbeat
{
    private const string KEY = 'platform:queue-heartbeat';

    private const int KEEP_SECONDS = 3600;

    public function __construct(private Cache $cache) {}

    public function beat(): void
    {
        $this->cache->put(self::KEY, Date::now()->getTimestamp(), self::KEEP_SECONDS);
    }

    public function secondsSinceLastBeat(): ?int
    {
        $beatAt = $this->cache->get(self::KEY);

        return is_int($beatAt) ? max(0, Date::now()->getTimestamp() - $beatAt) : null;
    }
}
