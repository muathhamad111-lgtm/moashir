<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\Criteria\AttachmentRequirementShape;

final class CriteriaNodeSnapshot
{
    /**
     * @return array<string, mixed>
     */
    public function ofMain(MainCriterion $main): array
    {
        return [
            'code' => $main->code,
            'name' => $main->name->toArray(),
            'description' => $main->description?->toArray(),
            'weight' => $main->weight->toDecimal(),
            'distribute_equally' => $main->distribute_equally,
            'group' => $main->group->value,
            'default_scoring_type' => $main->default_scoring_type->value,
            'attachments' => $this->attachmentsOf($main),
            'active' => $main->is_active,
            'order' => $main->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ofSub(SubCriterion $sub): array
    {
        return [
            'code' => $sub->code,
            'name' => $sub->name->toArray(),
            'description' => $sub->description?->toArray(),
            'weight' => $sub->weight->toDecimal(),
            'distribute_equally' => $sub->distribute_equally,
            'evaluation_type' => $sub->evaluation_type->value,
            'scoring_type' => $sub->scoring_type->value,
            'default_point_scoring_type' => $sub->default_point_scoring_type->value,
            'has_cv' => $sub->has_cv,
            'assessor_note_label' => $sub->assessor_note_label?->toArray(),
            'active' => $sub->is_active,
            'order' => $sub->sort_order,
            'attachments' => $this->attachmentsOf($sub),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ofPoint(GuidingPoint $point): array
    {
        return [
            'code' => $point->code,
            'description' => $point->description->toArray(),
            'weight' => $point->weight?->toDecimal(),
            'scoring_type' => $point->scoring_type->value,
            'active' => $point->is_active,
            'order' => $point->sort_order,
            'attachments' => $this->attachmentsOf($point),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attachmentsOf(MainCriterion|SubCriterion|GuidingPoint $owner): array
    {
        $requirements = $owner->relationLoaded('attachmentRequirements')
            ? $owner->attachmentRequirements
            : $owner->attachmentRequirements()->get();

        return array_values($requirements->map(AttachmentRequirementShape::of(...))->all());
    }
}
