<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Support\ValueObjects\LocalizedText;

final readonly class CycleBinding
{
    public function __construct(public int $cycleId, public LocalizedText $name, public string $status, public int $criteriaVersionId, public int $openRequests) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->cycleId,
            'name' => $this->name->toArray(),
            'status' => $this->status,
            'criteria_version_id' => $this->criteriaVersionId,
            'open_requests' => $this->openRequests,
        ];
    }
}
