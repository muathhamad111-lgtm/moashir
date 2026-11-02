<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Criteria\ConsistencyProblemCode;
use App\Enums\Criteria\CriteriaNodeType;
use App\Support\ValueObjects\Weight;

final readonly class ConsistencyProblem
{
    public function __construct(
        public CriteriaNodeType $nodeType,
        public ?int $nodeId,
        public ?string $code,
        public ConsistencyProblemCode $problem,
        public ?Weight $expected = null,
        public ?Weight $actual = null,
    ) {}

    public function fieldKey(): string
    {
        return $this->nodeId === null ? 'nodes.version' : sprintf('nodes.%s.%d', $this->nodeType->value, $this->nodeId);
    }

    public function message(): string
    {
        return __('errors.criteria.problems.'.$this->problem->value, [
            'code' => (string) $this->code,
            'expected' => $this->expected?->toDecimal() ?? '',
            'actual' => $this->actual?->toDecimal() ?? '',
        ]);
    }

    /**
     * @return array{node_type: string, node_id: int|null, code: string|null, problem: string, expected_weight: string|null, actual_weight: string|null}
     */
    public function toArray(): array
    {
        return [
            'node_type' => $this->nodeType->value,
            'node_id' => $this->nodeId,
            'code' => $this->code,
            'problem' => $this->problem->value,
            'expected_weight' => $this->expected?->toDecimal(),
            'actual_weight' => $this->actual?->toDecimal(),
        ];
    }
}
