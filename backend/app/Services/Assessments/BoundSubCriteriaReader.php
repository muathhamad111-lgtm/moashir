<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\AttachmentSlot;
use App\Data\Assessments\BoundSub;
use App\Data\Assessments\BoundSubCriteria;
use App\Models\Criteria\AttachmentRequirement;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Database\Eloquent\Collection;

final readonly class BoundSubCriteriaReader
{
    public function __construct(private CriteriaTreeLoader $loader) {}

    public function of(CriteriaVersion $version): BoundSubCriteria
    {
        $subs = [];

        foreach ($this->active($this->loader->load($version)->mainCriteria) as $main) {
            foreach ($this->active($main->subCriteria) as $sub) {
                $subs[] = new BoundSub($sub->id, $sub->code, $sub->name, $sub->has_cv, $this->slotsOf($sub));
            }
        }

        return new BoundSubCriteria($subs);
    }

    /**
     * @return list<AttachmentSlot>
     */
    private function slotsOf(SubCriterion $sub): array
    {
        $requirements = $sub->attachmentRequirements->all();

        foreach ($this->active($sub->guidingPoints) as $point) {
            array_push($requirements, ...$point->attachmentRequirements->all());
        }

        return array_values(array_map(
            static fn (AttachmentRequirement $requirement): AttachmentSlot => new AttachmentSlot($requirement->key, $requirement->is_required),
            $requirements,
        ));
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
