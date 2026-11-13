<?php

declare(strict_types=1);

namespace App\Data\Insights;

final readonly class StrengthsGapsReport
{
    public function __construct(public InsightsSubject $subject, public StrengthsGaps $classes) {}
}
