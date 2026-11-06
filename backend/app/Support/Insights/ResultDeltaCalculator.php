<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\MainDelta;
use App\Data\Insights\ResultDelta;
use App\Data\Insights\ResultMain;
use App\Data\Insights\ResultSub;
use App\Data\Insights\ResultTree;
use App\Enums\Insights\DeltaComparison;
use App\Support\ValueObjects\PointsDelta;

final class ResultDeltaCalculator
{
    public function compare(ResultTree $current, ?ResultTree $previous): ResultDelta
    {
        if (! $previous instanceof ResultTree) {
            return new ResultDelta;
        }

        $mains = [];

        foreach ($current->mains as $main) {
            $mains[$main->code] = $this->mainDelta($main, $previous->main($main->code));
        }

        return new ResultDelta(
            PointsDelta::between($current->total->percent, $previous->total->percent),
            $previous->total->percent,
            $current->criteriaVersionId !== $previous->criteriaVersionId,
            $mains,
            array_values(array_filter($previous->mains, static fn (ResultMain $old): bool => ! $current->main($old->code) instanceof ResultMain)),
        );
    }

    private function mainDelta(ResultMain $main, ?ResultMain $previous): MainDelta
    {
        if (! $previous instanceof ResultMain) {
            return new MainDelta(DeltaComparison::NewInVersion);
        }

        $subs = [];

        foreach ($main->subs as $sub) {
            $old = $previous->sub($sub->code);

            if ($old instanceof ResultSub) {
                $subs[$sub->code] = PointsDelta::between($sub->figures->percent, $old->figures->percent);
            }
        }

        return new MainDelta(
            DeltaComparison::Matched,
            PointsDelta::between($main->figures->percent, $previous->figures->percent),
            $previous->figures->percent,
            $subs,
        );
    }
}
