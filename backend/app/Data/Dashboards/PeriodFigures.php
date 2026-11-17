<?php

declare(strict_types=1);

namespace App\Data\Dashboards;

final readonly class PeriodFigures
{
    /**
     * @param  array<string, array{submitted: int|null, completed: int|null}>  $paths  per assessment path; null where the path has no such step
     */
    public function __construct(
        public int $newEntities,
        public int $requestsSubmitted,
        public int $assessmentsCompleted,
        public ?string $accreditedAveragePercent,
        public string $paymentsCollected,
        public array $paths,
    ) {}
}
