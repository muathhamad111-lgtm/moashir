<?php

declare(strict_types=1);

namespace App\Data\Platform;

use App\Enums\Platform\ReadinessCheckStatus;
use App\Enums\Platform\ReadinessStatus;
use Carbon\CarbonImmutable;

final readonly class ReadinessReport
{
    /**
     * @param  list<ReadinessResult>  $results
     */
    public function __construct(public array $results, public CarbonImmutable $checkedAt, public bool $isDetailed) {}

    public function status(): ReadinessStatus
    {
        return match (true) {
            array_any($this->results, static fn (ReadinessResult $result): bool => $result->isBlocking()) => ReadinessStatus::NotReady,
            array_any($this->results, static fn (ReadinessResult $result): bool => $result->status === ReadinessCheckStatus::Failing) => ReadinessStatus::Degraded,
            default => ReadinessStatus::Ready,
        };
    }
}
