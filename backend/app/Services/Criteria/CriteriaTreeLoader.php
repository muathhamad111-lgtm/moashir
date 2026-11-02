<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;

final class CriteriaTreeLoader
{
    public function load(CriteriaVersion $version): CriteriaVersion
    {
        return $version->load([
            'mainCriteria.subCriteria.attachmentRequirements',
            'mainCriteria.subCriteria.guidingPoints.attachmentRequirements',
        ]);
    }

    public function loadMain(MainCriterion $main): MainCriterion
    {
        return $main->load(['subCriteria.attachmentRequirements', 'subCriteria.guidingPoints']);
    }

    public function loadSub(SubCriterion $sub): SubCriterion
    {
        return $sub->load(['mainCriterion.subCriteria', 'attachmentRequirements', 'guidingPoints.attachmentRequirements']);
    }

    public function loadPoint(GuidingPoint $point): GuidingPoint
    {
        return $point->load('attachmentRequirements');
    }
}
