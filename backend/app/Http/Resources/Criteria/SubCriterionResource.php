<?php

declare(strict_types=1);

namespace App\Http\Resources\Criteria;

use App\Models\Criteria\SubCriterion;
use App\Support\Criteria\ActiveWeights;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SubCriterion $resource
 */
final class SubCriterionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sub = $this->resource;
        $main = $sub->mainCriterion;
        $allocatedToPoints = ActiveWeights::total($sub->guidingPoints);

        return [
            'id' => $sub->id,
            'main_criterion_id' => $sub->main_criterion_id,
            'code' => $sub->code,
            'name' => $sub->name->toArray(),
            'description' => $sub->description?->toArray(),
            'weight' => $sub->weight->toDecimal(),
            'distribute_equally' => $sub->distribute_equally,
            'evaluation_type' => $sub->evaluation_type->value,
            'has_cv' => $sub->has_cv,
            'assessor_note_label' => $sub->assessor_note_label?->toArray(),
            'attachments' => AttachmentRequirementResource::collection($sub->attachmentRequirements)->resolve($request),
            'active' => $sub->is_active,
            'order' => $sub->sort_order,
            'available_weight' => $main->weight->minus(ActiveWeights::total($main->subCriteria, $sub->id))->toDecimal(),
            'parent_name' => $main->name->toArray(),
            'point_count' => $sub->guidingPoints->count(),
            'allocated_point_weight' => $allocatedToPoints->toDecimal(),
            'remaining_point_weight' => $sub->weight->minus($allocatedToPoints)->toDecimal(),
        ];
    }
}
