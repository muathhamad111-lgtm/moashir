<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Models\Criteria\AttachmentRequirement;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\Criteria\ActiveWeights;
use Illuminate\Database\Eloquent\Collection;

final class CriteriaTreeSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function serialize(CriteriaVersion $version): array
    {
        return [
            'version_id' => $version->id,
            'version_number' => $version->version_number,
            'label' => $version->label?->toArray(),
            'status' => $version->status->value,
            'scale_kind' => $version->scale_kind?->value,
            'published_at' => $version->published_at?->toIso8601String(),
            'total_weight' => ActiveWeights::total($version->mainCriteria)->toDecimal(),
            'mains' => array_map($this->main(...), $this->active($version->mainCriteria)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function main(MainCriterion $main): array
    {
        return [
            'id' => $main->id,
            'code' => $main->code,
            'order' => $main->sort_order,
            'group' => $main->group->value,
            'name' => $main->name->toArray(),
            'description' => $main->description?->toArray(),
            'weight' => $main->weight->toDecimal(),
            'subs' => array_map($this->sub(...), $this->active($main->subCriteria)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sub(SubCriterion $sub): array
    {
        return [
            'id' => $sub->id,
            'code' => $sub->code,
            'order' => $sub->sort_order,
            'name' => $sub->name->toArray(),
            'description' => $sub->description?->toArray(),
            'weight' => $sub->weight->toDecimal(),
            'evaluation_type' => $sub->evaluation_type->value,
            'has_cv' => $sub->has_cv,
            'assessor_note_label' => $sub->assessor_note_label?->toArray(),
            'attachments' => $this->attachments($sub->attachmentRequirements),
            'points' => $sub->scoresGuidingPoints() ? array_map($this->point(...), $this->active($sub->guidingPoints)) : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function point(GuidingPoint $point): array
    {
        return [
            'id' => $point->id,
            'code' => $point->code,
            'order' => $point->sort_order,
            'description' => $point->description->toArray(),
            'weight' => $point->weight->toDecimal(),
            'attachments' => $this->attachments($point->attachmentRequirements),
        ];
    }

    /**
     * @param  Collection<int, AttachmentRequirement>  $requirements
     * @return list<array{key: string, name: array{ar: string, en: string}, required: bool}>
     */
    private function attachments(Collection $requirements): array
    {
        return array_values($requirements->map(static fn (AttachmentRequirement $requirement): array => [
            'key' => $requirement->key,
            'name' => $requirement->name->toArray(),
            'required' => $requirement->is_required,
        ])->all());
    }

    /**
     * @template TNode of MainCriterion|SubCriterion|GuidingPoint
     *
     * @param  Collection<int, TNode>  $nodes
     * @return list<TNode>
     */
    private function active(Collection $nodes): array
    {
        return array_values($nodes->where('is_active', true)->all());
    }
}
