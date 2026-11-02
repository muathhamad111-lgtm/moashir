<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Services\Entities\EntityFreezeGuard;

final readonly class CycleOpenRequests
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return list<array{id: int, number: string, status: string}>
     */
    public function of(int $cycleId): array
    {
        return array_values($this->freezeGuard->excludeFrozen(Assessment::query()
            ->inCycle($cycleId)
            ->whereIn('status', AssessmentPath::Advanced->openStateValues()))
            ->orderBy('id')
            ->get(['id', 'request_number', 'status', 'path'])
            ->map(static fn (Assessment $assessment): array => [
                'id' => $assessment->id,
                'number' => $assessment->request_number,
                'status' => (string) $assessment->status->value,
            ])
            ->all());
    }
}
