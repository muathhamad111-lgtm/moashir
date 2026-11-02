<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Data\Criteria\CriteriaVersionSummary;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Criteria\CriteriaVersionSummarizer;

final readonly class ShowCriteriaVersion
{
    public function __construct(private CriteriaVersionSummarizer $summarizer) {}

    public function handle(CriteriaVersion $version): CriteriaVersionSummary
    {
        return $this->summarizer->summarize([$version])[0];
    }
}
