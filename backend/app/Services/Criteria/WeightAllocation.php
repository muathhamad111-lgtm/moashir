<?php

declare(strict_types=1);

namespace App\Services\Criteria;

use App\Data\Criteria\WeightBudget;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Support\Criteria\ActiveWeights;
use App\Support\ValueObjects\Weight;

final readonly class WeightAllocation
{
    public function __construct(private WeightGuard $guard) {}

    public function forSub(MainCriterion $main, ?SubCriterion $existing, ?Weight $requested, bool $isActive): Weight
    {
        return $this->guard->childWeight($this->budgetOfSubs($main, $existing), $requested, $isActive);
    }

    public function forPoint(SubCriterion $sub, ?GuidingPoint $existing, ?Weight $requested, bool $isActive): Weight
    {
        return $this->guard->childWeight($this->budgetOfPoints($sub, $existing), $requested, $isActive);
    }

    public function assertMainCoversSubs(MainCriterion $main, Weight $weight): void
    {
        $this->guard->assertCovers($weight, ActiveWeights::total($main->subCriteria()->get()));
    }

    public function assertSubCoversPoints(SubCriterion $sub, Weight $weight): void
    {
        $this->guard->assertCovers($weight, ActiveWeights::total($sub->guidingPoints()->get()));
    }

    private function budgetOfSubs(MainCriterion $main, ?SubCriterion $except): WeightBudget
    {
        return new WeightBudget($main->weight, ActiveWeights::total($main->subCriteria()->get(), $except?->id), $main->distribute_equally);
    }

    private function budgetOfPoints(SubCriterion $sub, ?GuidingPoint $except): WeightBudget
    {
        return new WeightBudget($sub->weight, ActiveWeights::total($sub->guidingPoints()->get(), $except?->id), $sub->distribute_equally);
    }
}
