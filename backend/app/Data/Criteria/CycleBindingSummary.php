<?php

declare(strict_types=1);

namespace App\Data\Criteria;

final readonly class CycleBindingSummary
{
    /**
     * @param  list<CycleBinding>  $runningCycles
     * @param  list<CycleBinding>  $drafts
     */
    public function __construct(public array $runningCycles, public array $drafts) {}

    /**
     * @return array{running: array<string, mixed>|null, running_cycles: list<array<string, mixed>>, requests_stay_on_cycle_version: bool, drafts: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'running' => ($this->runningCycles[0] ?? null)?->toArray(),
            'running_cycles' => array_map(static fn (CycleBinding $cycle): array => $cycle->toArray(), $this->runningCycles),
            'requests_stay_on_cycle_version' => true,
            'drafts' => array_map(static fn (CycleBinding $draft): array => $draft->toArray(), $this->drafts),
        ];
    }
}
