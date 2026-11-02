<?php

declare(strict_types=1);

namespace App\Support\Award;

use App\Data\Award\AwardResultsBoard;
use App\Models\Award\AwardRanking;
use App\Models\Award\AwardRegionApproval;
use App\Models\Entities\Region;
use App\Support\Entities\EntityPresenter;

final class AwardResultsMeta
{
    /**
     * @return array<string, mixed>
     */
    public static function of(AwardResultsBoard $board): array
    {
        $cycle = $board->cycle;

        return [
            'cycle_status' => $cycle->status->value,
            'regions' => array_values($cycle->regions->map(static fn (Region $region): array => self::region($board, $region))->all()),
            'national' => [
                'approved' => $cycle->isNationalApproved(),
                'approved_at' => $cycle->national_approved_at?->toIso8601String(),
                'announced_at' => $cycle->announced_at?->toIso8601String(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function region(AwardResultsBoard $board, Region $region): array
    {
        $approval = $board->approvals[$region->id] ?? null;
        $rows = array_filter($board->rankings, static fn (AwardRanking $ranking): bool => $ranking->region_id === $region->id);

        return [
            ...EntityPresenter::place($region),
            'approved' => $approval instanceof AwardRegionApproval,
            'approved_at' => $approval?->approved_at->toIso8601String(),
            'total' => count($rows),
            'ranked' => count(array_filter($rows, static fn (AwardRanking $ranking): bool => $ranking->regional_rank !== null)),
            'pending' => $board->pendingByRegion[$region->id] ?? 0,
        ];
    }
}
