<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\CriteriaNodeNames;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Criteria\CriteriaTreeLoader;

final readonly class CriteriaNodeNamesReader
{
    public function __construct(private CriteriaTreeLoader $loader) {}

    public function of(CriteriaVersion $version): CriteriaNodeNames
    {
        $mains = [];
        $subs = [];
        $points = [];

        foreach ($this->loader->load($version)->mainCriteria as $main) {
            $mains[$main->id] = $main->name;

            foreach ($main->subCriteria as $sub) {
                $subs[$sub->id] = $sub->name;

                foreach ($sub->guidingPoints as $point) {
                    $points[$point->id] = $point->description;
                }
            }
        }

        return new CriteriaNodeNames($mains, $subs, $points);
    }
}
