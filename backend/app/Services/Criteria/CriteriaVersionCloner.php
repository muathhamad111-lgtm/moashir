<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Models\Criteria\AttachmentRequirement;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use Illuminate\Database\Eloquent\Collection;

final class CriteriaVersionCloner
{
    public function cloneInto(CriteriaVersion $source, CriteriaVersion $target): void
    {
        foreach ($source->mainCriteria as $main) {
            $copy = $this->copyOf($main);
            $copy->forceFill(['criteria_version_id' => $target->id, 'cloned_from_id' => $main->id])->save();

            foreach ($main->subCriteria as $sub) {
                $this->cloneSub($sub, $copy);
            }
        }
    }

    private function cloneSub(SubCriterion $sub, MainCriterion $target): void
    {
        $copy = $this->copyOf($sub);
        $copy->forceFill(['main_criterion_id' => $target->id, 'cloned_from_id' => $sub->id])->save();
        $this->cloneAttachments($sub->attachmentRequirements, $copy);

        foreach ($sub->guidingPoints as $point) {
            $pointCopy = $this->copyOf($point);
            $pointCopy->forceFill(['sub_criterion_id' => $copy->id, 'cloned_from_id' => $point->id])->save();
            $this->cloneAttachments($point->attachmentRequirements, $pointCopy);
        }
    }

    /**
     * @param  Collection<int, AttachmentRequirement>  $requirements
     */
    private function cloneAttachments(Collection $requirements, SubCriterion|GuidingPoint $owner): void
    {
        foreach ($requirements as $requirement) {
            $owner->attachmentRequirements()->create([
                'key' => $requirement->key,
                'name' => $requirement->name,
                'is_required' => $requirement->is_required,
                'sort_order' => $requirement->sort_order,
            ]);
        }
    }

    /**
     * @template TNode of MainCriterion|SubCriterion|GuidingPoint
     *
     * @param  TNode  $node
     * @return TNode
     */
    private function copyOf(MainCriterion|SubCriterion|GuidingPoint $node): MainCriterion|SubCriterion|GuidingPoint
    {
        return $node->replicate()->setRelations([]);
    }
}
