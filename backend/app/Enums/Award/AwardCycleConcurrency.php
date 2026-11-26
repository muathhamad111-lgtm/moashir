<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum AwardCycleConcurrency: string
{
    case Single = 'single';
    case Staggered = 'staggered';
    case Concurrent = 'concurrent';

    /**
     * @return list<AwardCycleStatus>
     */
    public function blockingStatuses(AwardCycleStatus $target): array
    {
        return match ($this) {
            self::Single => array_values(array_filter(AwardCycleStatus::cases(), static fn (AwardCycleStatus $status): bool => $status->isRunning())),
            self::Staggered => $target->acceptsApplications() ? [AwardCycleStatus::SubmissionOpen] : [],
            self::Concurrent => [],
        };
    }
}
