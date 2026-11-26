<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Enums\Award\AwardCycleConcurrency;
use App\Models\Award\AwardCycle;

final readonly class CycleOpenBlock
{
    public function __construct(public AwardCycleConcurrency $concurrency, public AwardCycle $blockingCycle) {}

    public function code(): string
    {
        return $this->concurrency === AwardCycleConcurrency::Single ? 'cycle.another_open' : 'cycle.another_open_for_submission';
    }

    /**
     * @return array{code: string, concurrency: string, cycle: array{id: int, name: array{ar: string, en: string}, year: int, status: string}}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code(),
            'concurrency' => $this->concurrency->value,
            'cycle' => [
                'id' => $this->blockingCycle->id,
                'name' => $this->blockingCycle->name->toArray(),
                'year' => $this->blockingCycle->year,
                'status' => $this->blockingCycle->status->value,
            ],
        ];
    }
}
