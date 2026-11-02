<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\SubCriterion;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Database\Eloquent\Builder;

final class CriteriaItemLabelsReader
{
    /**
     * @param  list<string>  $codes
     * @return array<string, LocalizedText>
     */
    public function in(int $versionId, array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        $labels = [];
        $inVersion = static fn (Builder $main): Builder => $main->where('criteria_version_id', $versionId);

        foreach (SubCriterion::query()->whereIn('code', $codes)->whereHas('mainCriterion', $inVersion)->get(['code', 'name']) as $sub) {
            $labels[$sub->code] = $sub->name;
        }

        foreach (GuidingPoint::query()->whereIn('code', $codes)->whereHas('subCriterion.mainCriterion', $inVersion)->get(['code', 'description']) as $point) {
            $labels[$point->code] = $point->description;
        }

        return $labels;
    }
}
