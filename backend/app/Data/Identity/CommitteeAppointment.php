<?php

declare(strict_types=1);

namespace App\Data\Identity;

use App\Support\ValueObjects\LocalizedText;
use Carbon\CarbonInterface;

final readonly class CommitteeAppointment
{
    public function __construct(
        public int $memberId,
        public int $cycleId,
        public LocalizedText $cycleName,
        public string $cycleStatus,
        public string $status,
        public bool $isSubstitute,
        public CarbonInterface $committeeStart,
        public CarbonInterface $committeeEnd,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->memberId,
            'award_cycle_id' => $this->cycleId,
            'cycle_name' => $this->cycleName->toArray(),
            'cycle_status' => $this->cycleStatus,
            'status' => $this->status,
            'is_substitute' => $this->isSubstitute,
            'committee_start' => $this->committeeStart->toIso8601String(),
            'committee_end' => $this->committeeEnd->toIso8601String(),
        ];
    }
}
