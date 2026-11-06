<?php

declare(strict_types=1);

namespace App\Data\Insights;

final readonly class MaturityReport
{
    public function __construct(
        public InsightsSubject $subject,
        public MaturityProfile $profile,
        public ResultDelta $delta,
        public MaturityTextBook $texts,
    ) {}
}
