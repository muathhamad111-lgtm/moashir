<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Support\ScalarValue;

final class PublishedMainCriterionCodes
{
    /**
     * @return list<string>
     */
    public function all(): array
    {
        return array_values(array_map(ScalarValue::string(...), MainCriterion::query()
            ->whereIn('criteria_version_id', CriteriaVersion::query()->published()->select('id'))
            ->pluck('code')
            ->all()));
    }
}
