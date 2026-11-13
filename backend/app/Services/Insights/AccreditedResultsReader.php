<?php

declare(strict_types=1);

namespace App\Services\Insights;

use App\Data\Insights\ResultCandidate;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Models\Certificates\Certificate;
use App\Models\Criteria\CriteriaVersion;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Collection;

final class AccreditedResultsReader
{
    /**
     * @return list<ResultCandidate>
     */
    public function forEntity(int $entityId): array
    {
        $assessments = Assessment::query()
            ->ownedBy($entityId)
            ->ofPath(AssessmentPath::Accredited)
            ->whereNotNull('current_result_snapshot_id')
            ->with(['currentResult', 'entity'])
            ->orderByDesc('id')
            ->get();

        return $this->candidatesOf($assessments);
    }

    public function latestCertificateOf(Assessment $assessment): ?Certificate
    {
        return Certificate::query()->where('assessment_id', $assessment->id)->latest('id')->first();
    }

    /**
     * @param  Collection<int, Assessment>  $assessments
     * @return list<ResultCandidate>
     */
    private function candidatesOf(Collection $assessments): array
    {
        $ids = $assessments->modelKeys();
        $certificates = Certificate::query()->whereIn('assessment_id', $ids)->orderBy('id')->get()->keyBy('assessment_id');
        $versions = CriteriaVersion::withTrashed()
            ->whereIn('id', $assessments->map(static fn (Assessment $assessment): int => ScalarValue::int($assessment->currentResult?->criteria_version_id))->unique()->values()->all())
            ->get()
            ->keyBy('id');

        return array_values($assessments->map(static fn (Assessment $assessment): ResultCandidate => new ResultCandidate(
            $assessment,
            $certificates->get($assessment->id),
            $versions->get(ScalarValue::int($assessment->currentResult?->criteria_version_id)),
        ))->all());
    }
}
