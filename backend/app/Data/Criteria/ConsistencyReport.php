<?php

declare(strict_types=1);

namespace App\Data\Criteria;

final readonly class ConsistencyReport
{
    /**
     * @param  list<ConsistencyProblem>  $problems
     */
    public function __construct(public array $problems) {}

    public function isConsistent(): bool
    {
        return $this->problems === [];
    }

    /**
     * @return list<array{node_type: string, node_id: int|null, code: string|null, problem: string, expected_weight: string|null, actual_weight: string|null}>
     */
    public function toArray(): array
    {
        return array_map(static fn (ConsistencyProblem $problem): array => $problem->toArray(), $this->problems);
    }
}
