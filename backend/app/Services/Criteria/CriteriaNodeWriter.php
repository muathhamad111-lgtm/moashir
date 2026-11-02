<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\GuidingPointAttributes;
use App\Data\Criteria\MainCriterionAttributes;
use App\Data\Criteria\SubCriterionAttributes;
use App\Exceptions\Criteria\CriteriaWeightRejected;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\ValueObjects\Weight;

final readonly class CriteriaNodeWriter
{
    public function __construct(
        private CriteriaCodeAllocator $codes,
        private SiblingOrder $order,
        private AttachmentRequirementWriter $attachments,
    ) {}

    public function addMain(CriteriaVersion $version, MainCriterionAttributes $attributes, bool $isActive): MainCriterion
    {
        $main = new MainCriterion;
        $this->fillMain($main, $attributes);
        $main->forceFill([
            'criteria_version_id' => $version->id,
            'code' => $this->codes->nextMainCode(),
            'sort_order' => $this->order->nextPosition($version->mainCriteria()),
            'is_active' => $isActive,
        ])->save();

        return $main;
    }

    public function addSub(MainCriterion $main, SubCriterionAttributes $attributes, bool $isActive): SubCriterion
    {
        $sub = new SubCriterion;
        $this->fillSub($sub, $attributes);
        $sub->forceFill([
            'main_criterion_id' => $main->id,
            'code' => $this->codes->nextSubCode($main),
            'evaluation_type' => $attributes->evaluationType,
            'sort_order' => $this->order->nextPosition($main->subCriteria()),
            'is_active' => $isActive,
        ])->save();
        $this->attachments->replace($sub, $attributes->attachments ?? []);

        return $sub;
    }

    public function addPoint(SubCriterion $sub, GuidingPointAttributes $attributes, bool $isActive): GuidingPoint
    {
        $point = new GuidingPoint;
        $this->fillPoint($point, $attributes);
        $point->forceFill([
            'sub_criterion_id' => $sub->id,
            'code' => $this->codes->nextPointCode($sub),
            'sort_order' => $this->order->nextPosition($sub->guidingPoints()),
            'is_active' => $isActive,
        ])->save();
        $this->attachments->replace($point, $attributes->attachments ?? []);

        return $point;
    }

    public function fillMain(MainCriterion $main, MainCriterionAttributes $attributes): void
    {
        $main->fill([
            'name' => $attributes->name,
            'description' => $attributes->description,
            'weight' => $attributes->weight,
            'distribute_equally' => $attributes->distributeEqually,
            'group' => $attributes->group,
        ]);
    }

    public function fillSub(SubCriterion $sub, SubCriterionAttributes $attributes): void
    {
        $sub->fill([
            'name' => $attributes->name,
            'description' => $attributes->description,
            'weight' => $this->requireWeight($attributes->weight),
            'distribute_equally' => $attributes->distributeEqually,
            'has_cv' => $attributes->hasCv,
            'assessor_note_label' => $attributes->assessorNoteLabel,
        ]);
    }

    public function fillPoint(GuidingPoint $point, GuidingPointAttributes $attributes): void
    {
        $point->fill([
            'description' => $attributes->description,
            'weight' => $this->requireWeight($attributes->weight),
        ]);
    }

    public function replaceAttachments(SubCriterion|GuidingPoint $owner, SubCriterionAttributes|GuidingPointAttributes $attributes): void
    {
        if ($attributes->attachments !== null) {
            $this->attachments->replace($owner, $attributes->attachments);
        }
    }

    private function requireWeight(?Weight $weight): Weight
    {
        return $weight ?? throw CriteriaWeightRejected::required();
    }
}
