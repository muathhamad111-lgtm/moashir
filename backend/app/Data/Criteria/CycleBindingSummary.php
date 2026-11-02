<?php

declare(strict_types=1);

namespace App\Data\Criteria;

final readonly class CycleBindingSummary
{
    /**
     * @param  list<CycleBinding>  $drafts
     */
    public function __construct(public ?CycleBinding $running, public array $drafts) {}

    /**
     * @return array{running: array<string, mixed>|null, requests_stay_on_cycle_version: bool, drafts: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'running' => $this->running?->toArray(),
            'requests_stay_on_cycle_version' => true,
            'drafts' => array_map(static fn (CycleBinding $draft): array => $draft->toArray(), $this->drafts),
        ];
    }
}
