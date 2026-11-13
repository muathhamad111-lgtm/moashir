<?php

declare(strict_types=1);

namespace App\Data\Insights;

use App\Enums\Insights\ResultIneligibility;

final readonly class ResultExclusion
{
    public function __construct(public ResultCandidate $candidate, public ResultIneligibility $reason) {}
}
