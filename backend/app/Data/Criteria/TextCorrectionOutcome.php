<?php

declare(strict_types=1);

namespace App\Data\Criteria;

use App\Models\Criteria\CriteriaEditEvent;
use App\Models\Criteria\CriteriaVersion;

final readonly class TextCorrectionOutcome
{
    public function __construct(
        public CriteriaVersion $version,
        public ?CriteriaEditEvent $editEvent,
        public CriteriaEditEffect $effect,
        public int $changeCount,
    ) {}
}
