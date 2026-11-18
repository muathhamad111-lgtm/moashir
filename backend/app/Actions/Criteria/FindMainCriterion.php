<?php

declare(strict_types=1);

namespace App\Actions\Criteria;

use App\Models\Criteria\MainCriterion;

final class FindMainCriterion
{
    public function handle(int $mainCriterionId): MainCriterion
    {
        return MainCriterion::query()->whereHas('criteriaVersion')->findOrFail($mainCriterionId);
    }
}
