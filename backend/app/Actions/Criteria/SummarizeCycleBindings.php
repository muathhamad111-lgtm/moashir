<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Contracts\Criteria\CycleCriteriaBindings;
use App\Data\Criteria\CycleBindingSummary;

final readonly class SummarizeCycleBindings
{
    public function __construct(private CycleCriteriaBindings $cycles) {}

    public function handle(): CycleBindingSummary
    {
        return $this->cycles->summary();
    }
}
