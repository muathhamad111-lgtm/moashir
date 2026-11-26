<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Support\ValueObjects\GapThresholds;
use App\Support\ValueObjects\MaturityBounds;
use Carbon\CarbonInterface;

final readonly class InsightsRules
{
    public function __construct(
        public GapThresholds $gaps,
        public MaturityBounds $bands,
        public ?CarbonInterface $effectiveSince = null,
    ) {}
}
