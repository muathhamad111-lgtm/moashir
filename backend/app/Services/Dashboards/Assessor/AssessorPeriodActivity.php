<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Assessor;

use App\Data\Dashboards\AssessorPeriodFigures;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\AssessmentCompletionRequest;
use App\Models\Identity\User;
use App\Services\Dashboards\ResultAverages;
use App\Support\Dashboards\AggregateRow;
use App\Support\ScalarValue;

/**
 * An assessor's figures for one reporting period: completed reviews (in all and per path),
 * their average length in days, the average accredited result and the completion requests sent.
 */
final readonly class AssessorPeriodActivity
{
    private const array PATHS = [AssessmentPath::Accredited, AssessmentPath::Advanced];

    public function __construct(private AssessorReviews $reviews, private ResultAverages $results) {}

    public function figures(User $assessor, ReportingPeriod $period): AssessorPeriodFigures
    {
        $completed = $this->completed($assessor, $period);

        return new AssessorPeriodFigures(
            completed: ScalarValue::int($completed['total'] ?? null),
            averageDays: $this->averageDays($completed),
            averagePercent: $this->results->averagePercent($this->reviews->accreditedResultsWithin($assessor, $period)),
            completionRequests: AssessmentCompletionRequest::query()
                ->where('requested_by_user_id', $assessor->id)
                ->where('created_at', '>=', $period->start->toIso8601String())
                ->where('created_at', '<', $period->end->toIso8601String())
                ->count(),
            completedByPath: $this->byPath($assessor, $period),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function completed(User $assessor, ReportingPeriod $period): array
    {
        return AggregateRow::fetch(
            $this->reviews->completedWithin($assessor, $period),
            'count(*) as total, round(cast(avg(extract(epoch from (ended_at - created_at))) / 86400 as numeric), 1) as average_days',
        );
    }

    /**
     * @param  array<string, mixed>  $completed
     */
    private function averageDays(array $completed): ?string
    {
        $days = ScalarValue::string($completed['average_days'] ?? null);

        return $days === '' ? null : $days;
    }

    /**
     * @return array<string, int>
     */
    private function byPath(User $assessor, ReportingPeriod $period): array
    {
        $conditions = [];
        foreach (self::PATHS as $path) {
            $conditions[$path->value] = ['assessments.path = ?', [$path->value]];
        }

        $counts = AggregateRow::counts(
            $this->reviews->completedWithin($assessor, $period)->join('assessments', 'assessments.id', '=', 'assessment_assignments.assessment_id'),
            $conditions,
        );

        return array_map(static fn (string $path): int => $counts[$path] ?? 0, array_combine(array_keys($conditions), array_keys($conditions)));
    }
}
