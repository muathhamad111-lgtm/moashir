<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Models\Criteria\AttachmentRequirement;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;

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
            'weight' => $point->weight->toDecimal(),
            'active' => $point->is_active,
            'order' => $point->sort_order,
            'attachments' => $this->attachmentsOf($point),
        ];
    }

    /**
     * @return list<array{key: string, name: array{ar: string, en: string}, required: bool}>
     */
    private function attachmentsOf(SubCriterion|GuidingPoint $owner): array
    {
        $requirements = $owner->relationLoaded('attachmentRequirements')
            ? $owner->attachmentRequirements
            : $owner->attachmentRequirements()->get();

        return array_values($requirements->map(static fn (AttachmentRequirement $requirement): array => [
            'key' => $requirement->key,
            'name' => $requirement->name->toArray(),
            'required' => $requirement->is_required,
        ])->all());
    }
}
