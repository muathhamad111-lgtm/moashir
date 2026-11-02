<?php

declare(strict_types=1);

namespace App\Data\Criteria;

final readonly class CriteriaSeedSub
{
    /**
     * @param  list<GuidingPointAttributes>  $points
     */
    public function __construct(public SubCriterionAttributes $attributes, public array $points) {}
}
