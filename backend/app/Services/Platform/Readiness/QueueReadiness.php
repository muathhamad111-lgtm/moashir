<?php

declare(strict_types=1);

namespace App\Services\Platform\Readiness;

use App\Contracts\Platform\ReadinessCheck;
use App\Enums\Platform\ReadinessCheckStatus;
use Illuminate\Contracts\Config\Repository;

final readonly class QueueReadiness implements ReadinessCheck
{
    private const string SYNC = 'sync';

    public function __construct(private QueueHeartbeat $heartbeat, private Repository $config, private ReadinessFailureLog $failures) {}

    public function name(): string
    {
        return 'queue';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function status(): ReadinessCheckStatus
    {
        if ($this->config->get('queue.default') === self::SYNC) {
            return ReadinessCheckStatus::Skipped;
        }

        $age = $this->heartbeat->secondsSinceLastBeat();

        return $age !== null && $age <= $this->config->integer('moashir.health.queue_heartbeat_max_age_seconds')
            ? ReadinessCheckStatus::Ok
            : $this->failures->record($this->name());
    }
}
