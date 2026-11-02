<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Enums\Criteria\CriteriaEditScope;
use App\Enums\Criteria\DraftCyclesChoice;

final readonly class PublishInstruction
{
    public function __construct(
        public ?CriteriaEditScope $scope,
        public ?string $reason,
        public DraftCyclesChoice $draftCycles = DraftCyclesChoice::Keep,
    ) {}
}
