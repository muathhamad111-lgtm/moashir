<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Data\Insights\ResultCandidate;
use App\Data\Insights\ResultFigures;
use App\Data\Insights\ResultMain;
use App\Data\Insights\ResultSub;
use App\Data\Insights\ResultTree;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\MainCriterion;
use App\Services\Assessments\CriteriaNodeNamesReader;
use App\Support\Assessments\ResultBreakdownPresenter;
use App\Support\ScalarValue;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\Percent;

final readonly class ResultTreeReader
{
    public function __construct(private CriteriaNodeNamesReader $names) {}

    public function of(ResultCandidate $candidate): ResultTree
    {
        $snapshot = $candidate->snapshot();
        $version = $candidate->criteriaVersion ?? CriteriaVersion::withTrashed()->findOrFail($snapshot->criteria_version_id);
        $breakdown = new ResultBreakdownPresenter($snapshot->breakdown, $this->names->of($version));
        $descriptions = $version->mainCriteria->mapWithKeys(static fn (MainCriterion $main): array => [$main->id => $main->description])->all();

        return new ResultTree(
            $version->id,
            $version->label,
            new ResultFigures($snapshot->total_weight->toDecimal(), $snapshot->total_score->toDisplay(), $snapshot->percent),
            $this->mainsOf($breakdown, $descriptions),
        );
    }

    /**
     * @param  array<int, LocalizedText|null>  $descriptions
     * @return list<ResultMain>
     */
    private function mainsOf(ResultBreakdownPresenter $breakdown, array $descriptions): array
    {
        $subsByMain = [];

        foreach ($breakdown->subs() as $sub) {
            $mainId = ScalarValue::int($sub['main_criterion_id'] ?? null);
            $subsByMain[$mainId][] = new ResultSub(
                ScalarValue::string($sub['code'] ?? null),
                $this->textOf($sub['name'] ?? null),
                $this->figuresOf($sub),
                count($subsByMain[$mainId] ?? []),
            );
        }

        $mains = [];

        foreach ($breakdown->mains() as $position => $main) {
            $id = ScalarValue::int($main['criterion_id'] ?? null);
            $mains[] = new ResultMain(
                ScalarValue::string($main['code'] ?? null),
                $this->textOf($main['name'] ?? null),
                $descriptions[$id] ?? null,
                $this->figuresOf($main),
                $position,
                $subsByMain[$id] ?? [],
            );
        }

        return $mains;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private function figuresOf(array $node): ResultFigures
    {
        return new ResultFigures(
            ScalarValue::string($node['weight'] ?? null, '0'),
            ScalarValue::string($node['earned'] ?? null, '0'),
            Percent::of(ScalarValue::string($node['percent'] ?? null, '0')),
        );
    }

    private function textOf(mixed $value): ?LocalizedText
    {
        return is_array($value) ? LocalizedText::fromArray($value) : null;
    }
}
