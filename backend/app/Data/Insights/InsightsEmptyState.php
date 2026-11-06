<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\InsightsEmptyReason;

final readonly class InsightsEmptyState
{
    public function __construct(public InsightsEmptyReason $reason, public ?int $requestId = null) {}
}
