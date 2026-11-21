<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Overview;

use App\Data\Dashboards\ReportingPeriod;
use App\Data\Dashboards\TrendSeries;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentStatus;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Dashboards\AssessmentTotals;

/**
 * Per-bucket series across a reporting period (a day, a week or a month each,
 * by the period's length). Buckets with no activity carry a zero.
 */
final readonly class ActivityTrends
{
    private const string MONEY_ZERO = '0.00';

    public function __construct(private AssessmentTotals $assessments, private PeriodBuckets $buckets) {}

    public function of(ReportingPeriod $period): TrendSeries
    {
        $operational = $this->assessments->operational();

        return new TrendSeries($period->bucket(), $this->buckets->keys($period), [
            'entities' => $this->buckets->counts(Entity::query()->notErased(), 'entities.created_at', $period),
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
                Payment::query()->where('status', PaymentStatus::Paid->value),
                'paid_at',
                $period,
                'cast(coalesce(sum(total), 0) as numeric(14, 2))',
                self::MONEY_ZERO,
            ),
        ]);
    }
}
