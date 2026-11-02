<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\ScoringMain;
use App\Data\Assessments\ScoringPoint;
use App\Data\Assessments\ScoringSub;
use App\Data\Assessments\ScoringTree;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Services\Criteria\CriteriaTreeLoader;
use Illuminate\Database\Eloquent\Collection;

final readonly class ScoringTreeBuilder
{
    public function __construct(private CriteriaTreeLoader $loader) {}

    public function build(CriteriaVersion $version): ScoringTree
    {
        $loaded = $this->loader->load($version);

        return new ScoringTree($loaded->id, array_map($this->main(...), $this->active($loaded->mainCriteria)));
    }

    private function main(MainCriterion $main): ScoringMain
    {
        return new ScoringMain($main->id, $main->code, $main->weight, array_map($this->sub(...), $this->active($main->subCriteria)));
    }

    private function sub(SubCriterion $sub): ScoringSub
    {
        $points = $sub->scoresGuidingPoints() ? array_map($this->point(...), $this->active($sub->guidingPoints)) : [];

        return new ScoringSub($sub->id, $sub->code, $sub->weight, $sub->evaluation_type, $points);
    }

    private function point(GuidingPoint $point): ScoringPoint
    {
        return new ScoringPoint($point->id, $point->code, $point->weight);
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
