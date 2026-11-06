<?php

declare(strict_types=1);

namespace App\Data\Audit;

use Carbon\CarbonImmutable;

final readonly class AuditLogFilters
{
    public function __construct(
        public ?CarbonImmutable $from = null,
        public ?CarbonImmutable $to = null,
        public ?int $actorId = null,
        public ?string $event = null,
        public ?string $subjectType = null,
        public ?int $subjectId = null,
        public ?string $ip = null,
        public int $perPage = 25,
    ) {}

    /**
     * @return array<string, scalar|null>
     */
    public function describe(): array
    {
        return [
            'from' => $this->from?->toIso8601String(),
            'to' => $this->to?->toIso8601String(),
            'actor_id' => $this->actorId,
            'event' => $this->event,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'ip' => $this->ip !== null,
        ];
    }
}
