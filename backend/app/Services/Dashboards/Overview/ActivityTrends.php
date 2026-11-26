<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Overview;

use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Data\Dashboards\TrendSeries;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentStatus;
use App\Models\Finance\Payment;
use App\Services\Dashboards\AssessmentTotals;

/**
 * Per-bucket series across a reporting period (a day, a week or a month each,
 * by the period's length). Buckets with no activity carry a zero.
 */
final readonly class ActivityTrends
{
    private const string MONEY_ZERO = '0.00';

    public function __construct(
        private AssessmentTotals $assessments,
        private PeriodBuckets $buckets,
        private EntityPopulation $population,
    ) {}

    public function of(ReportingPeriod $period, BoardFilter $filter): TrendSeries
    {
        $operational = $this->assessments->operational();
        $filter->applyToAssessments($operational);
        $paid = Payment::query()->where('status', PaymentStatus::Paid->value);
        $filter->applyToPayments($paid);

        return new TrendSeries($period->bucket(), $this->buckets->keys($period), [
            'entities' => $this->buckets->counts($this->population->current($filter), 'entities.created_at', $period),
            'requests' => $this->buckets->counts(
                (clone $operational)->whereIn('path', [AssessmentPath::Accredited->value, AssessmentPath::Advanced->value]),
                'submitted_at',
                $period,
            ),
            'completed' => $this->buckets->counts(
                (clone $operational)->whereIn('path', [AssessmentPath::SelfAssessment->value, AssessmentPath::Accredited->value]),
                'completed_at',
                $period,
            ),
            'collected' => $this->buckets->sums(
                $paid,
                'paid_at',
                $period,
                'cast(coalesce(sum(total), 0) as numeric(14, 2))',
                self::MONEY_ZERO,
            ),
        ]);
    }
}
