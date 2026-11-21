<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

/**
 * What one assessor did inside a reporting period.
 */
final readonly class AssessorPeriodFigures
{
    /**
     * @param  array<string, int>  $completedByPath  completed reviews per assessment path
     */
    public function __construct(
        public int $completed,
        public ?string $averageDays,
        public ?string $averagePercent,
        public int $completionRequests,
        public array $completedByPath,
    ) {}
}
