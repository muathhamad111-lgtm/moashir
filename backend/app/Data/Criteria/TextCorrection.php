<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Criteria\CriteriaNodeType;
use App\Support\ValueObjects\LocalizedText;

final readonly class TextCorrection
{
    /**
     * @param  array<string, LocalizedText|null>  $fields
     */
    public function __construct(
        public CriteriaNodeType $nodeType,
        public int $nodeId,
        public array $fields,
    ) {}
}
