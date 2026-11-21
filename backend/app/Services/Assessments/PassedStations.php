<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Models\Assessments\Assessment;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class PassedStations
{
    private const string TABLE = 'assessment_passed_stations';

    /**
     * @return list<int>
     */
    public function of(Assessment $assessment): array
    {
        return array_values(DB::table(self::TABLE)
            ->where('assessment_id', $assessment->id)
            ->orderBy('main_criterion_id')
            ->pluck('main_criterion_id')
            ->map(static fn (mixed $id): int => ScalarValue::int($id))
            ->all());
    }

    /**
     * Passing a station twice keeps its first time; the unique index guards concurrent requests.
     */
    public function pass(Assessment $assessment, int $mainCriterionId): void
    {
        DB::table(self::TABLE)->insertOrIgnore([
            'assessment_id' => $assessment->id,
            'main_criterion_id' => $mainCriterionId,
            'passed_at' => Date::now(),
        ]);
    }
}
