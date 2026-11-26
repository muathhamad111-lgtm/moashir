<?php

declare(strict_types=1);

namespace App\Services\Platform\Readiness;

use App\Contracts\Platform\ReadinessCheck;
use App\Enums\Platform\ReadinessCheckStatus;
use ErrorException;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;

final readonly class DiskSpaceReadiness implements ReadinessCheck
{
    private const int BYTES_PER_MEGABYTE = 1048576;

    public function __construct(private Application $app, private Repository $config, private ReadinessFailureLog $failures) {}

    public function name(): string
    {
        return 'disk_space';
    }

    public function isCritical(): bool
    {
        return true;
    }

    public function status(): ReadinessCheckStatus
    {
        $minimum = $this->config->integer('moashir.health.min_free_disk_mb') * self::BYTES_PER_MEGABYTE;

        try {
            $free = disk_free_space($this->app->storagePath());
        } catch (ErrorException $failure) {
            return $this->failures->record($this->name(), $failure);
        }

        return $free !== false && $free >= $minimum ? ReadinessCheckStatus::Ok : $this->failures->record($this->name());
    }
}
