<?php

declare(strict_types=1);

namespace App\Support\Award;

use App\Models\Assessments\Assessment;
use App\Models\Award\AwardRanking;
use App\Models\Entities\Region;
use App\Support\Entities\EntityPresenter;

final class AwardOutcomePresenter
{
    /**
     * @return array{status: string, region: array<string, mixed>|null, regional_rank: int|null, national_rank: int|null}
     */
    public static function present(Assessment $assessment, ?AwardRanking $ranking): array
    {
        return [
            'status' => (string) $assessment->status->value,
            'region' => $assessment->region instanceof Region ? EntityPresenter::place($assessment->region) : null,
            'regional_rank' => $ranking?->regional_rank,
            'national_rank' => $ranking?->national_rank,
        ];
    }
}
