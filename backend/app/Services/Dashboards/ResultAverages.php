<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentResultSnapshot;
use App\Support\Dashboards\AggregateRow;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final class ResultAverages
{
    /**
     * @param  Builder<Assessment>  $assessments
     */
    public function averagePercent(Builder $assessments): ?string
    {
        $row = AggregateRow::fetch(
            AssessmentResultSnapshot::query()->whereIn('id', (clone $assessments)->reorder()->whereNotNull('current_result_snapshot_id')->select('current_result_snapshot_id')),
            'round(avg(percent), 2) as average_percent',
        );

        $average = ScalarValue::string($row['average_percent'] ?? null);

        return $average === '' ? null : $average;
    }
}
