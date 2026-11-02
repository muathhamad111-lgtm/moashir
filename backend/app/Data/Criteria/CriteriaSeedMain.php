<?php

declare(strict_types=1);

namespace App\Data\Criteria;

final readonly class CriteriaSeedMain
{
    /**
     * @param  list<CriteriaSeedSub>  $subs
     */
    public function __construct(public MainCriterionAttributes $attributes, public array $subs) {}
}
