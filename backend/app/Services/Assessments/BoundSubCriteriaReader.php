<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\AttachmentSlot;
use App\Data\Assessments\AttachmentSlots;
use App\Data\Assessments\BoundMain;
use App\Data\Assessments\BoundSub;
use App\Data\Assessments\BoundSubCriteria;
use App\Enums\Files\EvidenceFileType;
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
        $mains = [];
        $subs = [];

        foreach ($this->active($this->loader->load($version)->mainCriteria) as $main) {
            $mains[] = new BoundMain($main->id, $main->code, $main->name, $this->slotsFrom($main->attachmentRequirements->all()));

            foreach ($this->active($main->subCriteria) as $sub) {
                $subs[] = new BoundSub($sub->id, $sub->code, $sub->name, $sub->has_cv, $this->slotsOf($sub));
            }
        }

        return new BoundSubCriteria($subs, $mains);
    }

    /**
     * A sub-criterion's slots: its own attachments, then those of its active indicators. A directly evaluated
     * sub-criterion keeps descriptive indicators whose attachments are required evidence too (operator, 2026-11-22).
     */
    private function slotsOf(SubCriterion $sub): AttachmentSlots
    {
        $requirements = $sub->attachmentRequirements->all();

        foreach ($this->active($sub->guidingPoints) as $point) {
            array_push($requirements, ...$point->attachmentRequirements->all());
        }

        return $this->slotsFrom($requirements);
    }

    /**
     * @param  array<int, AttachmentRequirement>  $requirements
     */
    private function slotsFrom(array $requirements): AttachmentSlots
    {
        return new AttachmentSlots(array_values(array_map(
            static fn (AttachmentRequirement $requirement): AttachmentSlot => new AttachmentSlot(
                $requirement->key,
                $requirement->is_required,
                array_values(array_filter(array_map(EvidenceFileType::tryFrom(...), $requirement->allowed_types ?? []))),
                $requirement->max_files,
            ),
            $requirements,
        )));
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
