<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Criteria\CriteriaChangeType;
use App\Enums\Criteria\CriteriaNodeType;

final readonly class CriteriaChange
{
    public const array SCORE_AFFECTING_FIELDS = ['weight', 'evaluation_type', 'active'];

    /**
     * @param  list<string>  $fields
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    public function __construct(
        public CriteriaNodeType $nodeType,
        public string $code,
        public CriteriaChangeType $change,
        public ?int $fromNodeId,
        public ?int $toNodeId,
        public array $fields = [],
        public array $before = [],
        public array $after = [],
    ) {}

    public function isScoreAffecting(): bool
    {
        return match ($this->change) {
            CriteriaChangeType::Added, CriteriaChangeType::Removed => true,
            CriteriaChangeType::Modified => array_intersect($this->fields, self::SCORE_AFFECTING_FIELDS) !== [],
            CriteriaChangeType::TextCorrected => false,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'node_type' => $this->nodeType->value,
            'code' => $this->code,
            'change' => $this->change->value,
            'from_node_id' => $this->fromNodeId,
            'to_node_id' => $this->toNodeId,
            'fields' => $this->fields,
            'before' => $this->before,
            'after' => $this->after,
            'score_affecting' => $this->isScoreAffecting(),
        ];
    }
}
