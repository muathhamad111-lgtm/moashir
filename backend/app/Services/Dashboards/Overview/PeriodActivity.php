<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Overview;

use App\Data\Dashboards\PeriodFigures;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentStatus;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Dashboards\AssessmentTotals;
use App\Services\Dashboards\ResultAverages;
use App\Support\Dashboards\AggregateRow;
use App\Support\ScalarValue;

/**
 * What happened inside a reporting period: registrations, submitted requests,
 * completed assessments, the accredited average and the money collected.
 */
final readonly class PeriodActivity
{
    private const string MONEY_ZERO = '0.00';

    private const string WITHIN = '%s >= ? and %s < ?';

    public function __construct(
        private AssessmentTotals $assessments,
        private ResultAverages $results,
    ) {}

    public function figures(ReportingPeriod $period): PeriodFigures
    {
        $counts = $this->assessmentCounts($period);

        return new PeriodFigures(
            newEntities: Entity::query()->notErased()
                ->where('entities.created_at', '>=', $period->start->toIso8601String())
                ->where('entities.created_at', '<', $period->end->toIso8601String())
                ->count(),
            requestsSubmitted: $counts['accredited_submitted'] + $counts['advanced_submitted'],
            assessmentsCompleted: $counts['self_completed'] + $counts['accredited_completed'],
            accreditedAveragePercent: $this->results->averagePercent($this->assessments->operational()
                ->where('path', AssessmentPath::Accredited)
                ->where('status', AccreditedAssessmentStatus::Completed->value)
                ->where('completed_at', '>=', $period->start->toIso8601String())
                ->where('completed_at', '<', $period->end->toIso8601String())),
            paymentsCollected: $this->collected($period),
            paths: [
                AssessmentPath::SelfAssessment->value => ['submitted' => null, 'completed' => $counts['self_completed']],
                AssessmentPath::Accredited->value => ['submitted' => $counts['accredited_submitted'], 'completed' => $counts['accredited_completed']],
                AssessmentPath::Advanced->value => ['submitted' => $counts['advanced_submitted'], 'completed' => null],
            ],
        );
    }

    /**
     * @return array{self_completed: int, accredited_submitted: int, accredited_completed: int, advanced_submitted: int}
     */
    private function assessmentCounts(ReportingPeriod $period): array
    {
        $window = [$period->start->toIso8601String(), $period->end->toIso8601String()];
        $submitted = sprintf(self::WITHIN, 'submitted_at', 'submitted_at');
        $completed = sprintf(self::WITHIN, 'completed_at', 'completed_at');

        $counts = AggregateRow::counts($this->assessments->operational(), [
            'self_completed' => ['path = ? and '.$completed, [AssessmentPath::SelfAssessment->value, ...$window]],
            'accredited_submitted' => ['path = ? and '.$submitted, [AssessmentPath::Accredited->value, ...$window]],
            'accredited_completed' => ['path = ? and '.$completed, [AssessmentPath::Accredited->value, ...$window]],
            'advanced_submitted' => ['path = ? and '.$submitted, [AssessmentPath::Advanced->value, ...$window]],
        ]);

        return [
            'self_completed' => $counts['self_completed'] ?? 0,
            'accredited_submitted' => $counts['accredited_submitted'] ?? 0,
            'accredited_completed' => $counts['accredited_completed'] ?? 0,
            'advanced_submitted' => $counts['advanced_submitted'] ?? 0,
        ];
    }

    private function collected(ReportingPeriod $period): string
    {
        $row = AggregateRow::fetch(
            Payment::query()->where('status', PaymentStatus::Paid->value)
                ->where('paid_at', '>=', $period->start->toIso8601String())
                ->where('paid_at', '<', $period->end->toIso8601String()),
            'cast(coalesce(sum(total), 0) as numeric(14, 2)) as collected',
        );

        return ScalarValue::string($row['collected'] ?? null, self::MONEY_ZERO);
    }
}
