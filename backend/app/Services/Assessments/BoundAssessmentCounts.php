<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Contracts\Criteria\BoundAssessmentCounter;
use App\Contracts\Criteria\OpenAssessmentCounter;
use App\Data\Criteria\BoundAssessmentCount;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class BoundAssessmentCounts implements BoundAssessmentCounter, OpenAssessmentCounter
{
    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    public function countBoundTo(CriteriaVersion $version): int
    {
        return $this->openOperational(Assessment::query()->where('criteria_version_id', $version->id))->count();
    }

    public function countsFor(array $versionIds): array
    {
        if ($versionIds === []) {
            return [];
        }

        $totals = $this->perVersion(Assessment::query()->whereIn('criteria_version_id', $versionIds));
        $open = $this->perVersion($this->openOperational(Assessment::query()->whereIn('criteria_version_id', $versionIds)));
        $counts = [];

        foreach ($versionIds as $versionId) {
            $counts[$versionId] = new BoundAssessmentCount($totals[$versionId] ?? 0, $open[$versionId] ?? 0);
        }

        return $counts;
    }

    /**
     * @param  Builder<Assessment>  $query
     * @return Builder<Assessment>
     */
    private function openOperational(Builder $query): Builder
    {
        return $this->freezeGuard->excludeFrozen($query->open());
    }

    /**
     * @param  Builder<Assessment>  $query
     * @return array<int, int>
     */
    private function perVersion(Builder $query): array
    {
        $counts = [];

        $rows = $query->toBase()
            ->select('criteria_version_id')
            ->selectRaw('count(*) as bound_count')
            ->groupBy('criteria_version_id')
            ->get();

        foreach ($rows as $row) {
            $counts[ScalarValue::int($row->criteria_version_id)] = ScalarValue::int($row->bound_count);
        }

        return $counts;
    }
}
