<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\BandedMain;
use App\Data\Insights\BandedSub;
use App\Data\Insights\MaturityProfile;
use App\Data\Insights\ResultMain;
use App\Data\Insights\ResultSub;
use App\Data\Insights\ResultTree;
use App\Support\ValueObjects\MaturityBounds;

final class MaturityBandClassifier
{
    public function profileOf(ResultTree $tree, MaturityBounds $rules): MaturityProfile
    {
        return new MaturityProfile(
            $rules,
            $rules->bandFor($tree->total->percent),
            array_map(static fn (ResultMain $main): BandedMain => new BandedMain(
                $main,
                $rules->bandFor($main->figures->percent),
                array_map(static fn (ResultSub $sub): BandedSub => new BandedSub($sub, $rules->bandFor($sub->figures->percent)), $main->subs),
            ), $tree->mains),
        );
    }
}
