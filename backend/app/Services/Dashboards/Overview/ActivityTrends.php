<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Overview;

use App\Data\Dashboards\ReportingPeriod;
use App\Data\Dashboards\TrendSeries;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Dashboards\TrendBucket;
use App\Enums\Finance\PaymentStatus;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Dashboards\AssessmentTotals;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-bucket series across a reporting period (a day, a week or a month each,
 * by the period's length). Buckets with no activity carry a zero.
 */
final readonly class ActivityTrends
{
    private const string BUCKET_FORMAT = 'Y-m-d';

    private const string MONEY_ZERO = '0.00';

    public function __construct(private AssessmentTotals $assessments) {}

    public function of(ReportingPeriod $period): TrendSeries
    {
        $bucket = $period->bucket();
        $buckets = $this->buckets($period, $bucket);
        $operational = $this->assessments->operational();

        return new TrendSeries($bucket, $buckets, [
            'entities' => $this->filled($buckets, $this->counted(Entity::query()->notErased(), 'entities.created_at', $period), 0),
            'requests' => $this->filled($buckets, $this->counted(
                (clone $operational)->whereIn('path', [AssessmentPath::Accredited->value, AssessmentPath::Advanced->value]),
                'submitted_at',
                $period,
            ), 0),
            'completed' => $this->filled($buckets, $this->counted(
                (clone $operational)->whereIn('path', [AssessmentPath::SelfAssessment->value, AssessmentPath::Accredited->value]),
                'completed_at',
                $period,
            ), 0),
            'collected' => $this->filled($buckets, $this->collected($period), self::MONEY_ZERO),
        ]);
    }

    /**
     * @return list<string>
     */
    private function buckets(ReportingPeriod $period, TrendBucket $bucket): array
    {
        $keys = [];

        for ($cursor = $bucket->startOf($period->start); $cursor->lessThan($period->end); $cursor = $bucket->next($cursor)) {
            $keys[] = $cursor->format(self::BUCKET_FORMAT);
        }

        return $keys;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column
     * @return array<string, int|string>
     */
    private function counted(Builder $query, string $column, ReportingPeriod $period): array
    {
        return $this->grouped($query, $column, $period, 'count(*)');
    }

    /**
     * @return array<string, int|string>
     */
    private function collected(ReportingPeriod $period): array
    {
        return $this->grouped(
            Payment::query()->live()->where('status', PaymentStatus::Paid->value),
            'paid_at',
            $period,
            'cast(coalesce(sum(total), 0) as numeric(14, 2))',
        );
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  literal-string  $column
     * @param  literal-string  $aggregate
     * @return array<string, int|string>
     */
    private function grouped(Builder $query, string $column, ReportingPeriod $period, string $aggregate): array
    {
        $local = 'date_trunc(?, '.$column.' at time zone ?)';
        $bindings = [$period->bucket()->value, $period->start->getTimezone()->getName()];

        $rows = (clone $query)->toBase()->reorder()
            ->where($column, '>=', $period->start->toIso8601String())
            ->where($column, '<', $period->end->toIso8601String())
            ->selectRaw("to_char({$local}, 'YYYY-MM-DD') as bucket, {$aggregate} as amount", $bindings)
            // Grouped by the first selected column: Postgres would not match a repeated parameterised expression.
            ->groupByRaw('1')
            ->get();

        $values = [];

        foreach ($rows as $row) {
            $columns = get_object_vars($row);
            $amount = $columns['amount'] ?? null;
            $values[ScalarValue::string($columns['bucket'] ?? null)] = is_int($amount) ? $amount : ScalarValue::string($amount);
        }

        return $values;
    }

    /**
     * @param  list<string>  $buckets
     * @param  array<string, int|string>  $values
     * @return list<int|string>
     */
    private function filled(array $buckets, array $values, int|string $zero): array
    {
        return array_map(static fn (string $bucket): int|string => is_int($zero)
            ? ScalarValue::int($values[$bucket] ?? null)
            : ScalarValue::string($values[$bucket] ?? null, $zero), $buckets);
    }
}
