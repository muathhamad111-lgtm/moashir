<?php

declare(strict_types=1);

namespace App\Data\Insights;

final readonly class ExecutiveSummaryView
{
    public function __construct(public ExecutiveSummary $summary, public ?string $logoUrl) {}
}
