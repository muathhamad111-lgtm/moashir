<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Criteria\CriterionGroup;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Weight;

final readonly class MainCriterionAttributes
{
    public function __construct(
        public LocalizedText $name,
        public ?LocalizedText $description,
        public Weight $weight,
        public bool $distributeEqually,
        public CriterionGroup $group,
    ) {}
}
