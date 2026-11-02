<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Models\Criteria\MainCriterion;
use App\Support\Criteria\ActiveWeights;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MainCriterion $resource
 */
final class MainCriterionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $main = $this->resource;
        $allocated = ActiveWeights::total($main->subCriteria);

        return [
            'id' => $main->id,
            'version_id' => $main->criteria_version_id,
            'code' => $main->code,
            'name' => $main->name->toArray(),
            'description' => $main->description?->toArray(),
            'weight' => $main->weight->toDecimal(),
            'distribute_equally' => $main->distribute_equally,
            'group' => $main->group->value,
            'active' => $main->is_active,
            'order' => $main->sort_order,
            'sub_count' => $main->subCriteria->count(),
            'allocated_weight' => $allocated->toDecimal(),
            'remaining_weight' => $main->weight->minus($allocated)->toDecimal(),
            'weight_balanced' => $allocated->equals($main->weight),
        ];
    }
}
