<?php

declare(strict_types=1);

namespace App\Data\Award;

use App\Models\Award\AwardCycle;

final readonly class AdvancedEligibility
{
    /**
     * @param  list<EligibilityCheck>  $checks
     */
    public function __construct(public ?AwardCycle $cycle, public bool $windowOpen, public array $checks) {}

    public function meetsConditions(): bool
    {
        return $this->failures() === [];
    }

    public function isEligible(): bool
    {
        return $this->cycle instanceof AwardCycle && $this->windowOpen && $this->meetsConditions();
    }

    /**
     * @return list<EligibilityCheck>
     */
    public function failures(): array
    {
        return array_values(array_filter($this->checks, static fn (EligibilityCheck $check): bool => ! $check->passed));
    }

    /**
     * @return list<array{rule: string, passed: bool, required: int|string|null, actual: int|string|null}>
     */
    public function failuresToArray(): array
    {
        return array_map(static fn (EligibilityCheck $check): array => $check->toArray(), $this->failures());
    }

    /**
     * @return array{eligible: bool, window_open: bool, conditions: list<array{rule: string, passed: bool, required: int|string|null, actual: int|string|null}>}
     */
    public function toArray(): array
    {
        return [
            'eligible' => $this->isEligible(),
            'window_open' => $this->windowOpen,
            'conditions' => array_map(static fn (EligibilityCheck $check): array => $check->toArray(), $this->checks),
        ];
    }
}
