<?php

declare(strict_types=1);

use App\Data\Insights\ResultFigures;
use App\Data\Insights\ResultMain;
use App\Data\Insights\ResultSub;
use App\Data\Insights\ResultTree;
use App\Support\ValueObjects\Percent;

/**
 * @param  array<string, array<string, array{0: string, 1: string}>>  $mains  main code => sub code => [weight, percent]
 */
function resultTree(array $mains, int $versionId = 1): ResultTree
{
    $built = [];
    $totalWeight = 0;
    $totalEarned = 0;

    foreach (array_keys($mains) as $mainPosition => $mainCode) {
        $subs = [];
        $mainWeight = 0;
        $mainEarned = 0;

        foreach (array_keys($mains[$mainCode]) as $subPosition => $subCode) {
            [$weight, $percent] = $mains[$mainCode][$subCode];
            $earned = (int) round((float) $weight * (float) $percent);
            $mainWeight += (int) round((float) $weight * 100);
            $mainEarned += $earned;
            $subs[] = new ResultSub($subCode, null, figures($weight, $earned, $percent), $subPosition);
        }

        $totalWeight += $mainWeight;
        $totalEarned += $mainEarned;
        $built[] = new ResultMain($mainCode, null, null, figures(number_format($mainWeight / 100, 2, '.', ''), $mainEarned, percentOf($mainEarned, $mainWeight)), $mainPosition, $subs);
    }

    return new ResultTree($versionId, null, figures(number_format($totalWeight / 100, 2, '.', ''), $totalEarned, percentOf($totalEarned, $totalWeight)), $built);
}

function figures(string $weight, int $earnedHundredths, string $percent): ResultFigures
{
    return new ResultFigures($weight, number_format($earnedHundredths / 100, 2, '.', ''), Percent::of($percent));
}

function percentOf(int $earned, int $weight): string
{
    return $weight === 0 ? '0' : number_format($earned * 100 / $weight, 4, '.', '');
}
