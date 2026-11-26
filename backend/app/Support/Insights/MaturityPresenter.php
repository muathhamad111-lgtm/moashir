<?php

declare(strict_types=1);

namespace App\Support\Insights;

use App\Data\Insights\BandedMain;
use App\Data\Insights\BandedSub;
use App\Data\Insights\MainDelta;
use App\Data\Insights\MaturityReport;
use App\Data\Insights\MaturityTextBook;
use App\Data\Insights\ResultMain;
use App\Support\ValueObjects\MaturityBounds;
use App\Support\ValueObjects\Percent;

final class MaturityPresenter
{
    /**
     * @return array<string, mixed>
     */
    public static function present(MaturityReport $report): array
    {
        $subject = $report->subject;
        $rules = $report->profile->rules;
        $delta = $report->delta;

        return [
            'result' => ResultRefPresenter::present($subject->result),
            'previous' => ResultRefPresenter::presentOptional($subject->previous),
            'versions_differ' => $delta->versionsDiffer,
            'rules' => [...$rules->toArray(), 'effective_since' => $subject->rules->effectiveSince?->toIso8601String()],
            'overall' => [
                'percent' => $subject->tree->total->percent->toDisplay(),
                'band' => $report->profile->overall->value,
                'delta_pp' => $delta->total?->toDisplay(),
                'previous_band' => self::bandOf($rules, $delta->previousTotal),
            ],
            'mains' => array_map(static fn (BandedMain $main): array => self::main($main, $delta->forMain($main->main->code), $rules, $report->texts), $report->profile->mains),
            'removed_since_previous' => array_map(static fn (ResultMain $removed): array => [
                'code' => $removed->code,
                'name' => $removed->name?->toArray(),
                'previous_percent' => $removed->figures->percent->toDisplay(),
            ], $delta->removed),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function main(BandedMain $banded, MainDelta $delta, MaturityBounds $rules, MaturityTextBook $texts): array
    {
        $main = $banded->main;

        return [
            'code' => $main->code,
            'name' => $main->name?->toArray(),
            'description' => $main->description?->toArray(),
            'weight' => $main->figures->weight,
            'earned' => $main->figures->earned,
            'percent' => $main->figures->percent->toDisplay(),
            'band' => $banded->band->value,
            'interpretation' => $texts->resolve($banded->band, $main->code)?->toArray(),
            'comparison' => $delta->comparison->value,
            'delta_pp' => $delta->delta?->toDisplay(),
            'previous_band' => self::bandOf($rules, $delta->previousPercent),
            'subs' => array_map(static fn (BandedSub $sub): array => [
                'code' => $sub->sub->code,
                'name' => $sub->sub->name?->toArray(),
                'percent' => $sub->sub->figures->percent->toDisplay(),
                'band' => $sub->band->value,
                'delta_pp' => $delta->forSub($sub->sub->code)?->toDisplay(),
            ], $banded->subs),
        ];
    }

    private static function bandOf(MaturityBounds $rules, ?Percent $percent): ?string
    {
        return $percent instanceof Percent ? $rules->bandFor($percent)->value : null;
    }
}
