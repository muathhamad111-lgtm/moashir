<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Geography;

use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Finance\PaymentStatus;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Dashboards\AssessmentTotals;
use App\Support\ScalarValue;
use Illuminate\Database\Query\Builder;

/**
 * One grouped query per measure, each returning a row per city and a row per region
 * (Postgres grouping sets), keyed by the entity's region and city.
 */
final readonly class LocationTotals
{
    private const string LOCATION_SELECT = 'entities.region_id, entities.city_id, grouping(entities.city_id) as is_region, ';

    private const string GROUPING = 'grouping sets ((entities.region_id, entities.city_id), (entities.region_id))';

    public function __construct(private AssessmentTotals $assessments) {}

    /**
     * @return array<string, int>
     */
    public function entities(): array
    {
        return $this->counts(Entity::query()->notErased()->toBase(), 'count(*)');
    }

    /**
     * @return array<string, int>
     */
    public function requests(ReportingPeriod $period): array
    {
        $query = $this->located($this->assessments->operational()->toBase(), 'assessments.entity_id')
            ->whereIn('assessments.path', [AssessmentPath::Accredited->value, AssessmentPath::Advanced->value]);

        return $this->counts($this->within($query, 'assessments.submitted_at', $period), 'count(*)');
    }

    /**
     * @return array<string, string>
     */
    public function averagePercents(ReportingPeriod $period): array
    {
        $query = $this->located($this->assessments->operational()->toBase(), 'assessments.entity_id')
            ->join('assessment_result_snapshots as snapshots', 'snapshots.id', '=', 'assessments.current_result_snapshot_id')
            ->where('assessments.path', AssessmentPath::Accredited->value)
            ->where('assessments.status', AccreditedAssessmentStatus::Completed->value);

        return $this->values($this->within($query, 'assessments.completed_at', $period), 'round(avg(snapshots.percent), 2)');
    }

    /**
     * @return array<string, string>
     */
    public function collected(ReportingPeriod $period): array
    {
        $query = $this->located(Payment::query()->toBase(), 'payments.entity_id')
            ->where('payments.status', PaymentStatus::Paid->value);

        return $this->values($this->within($query, 'payments.paid_at', $period), 'cast(sum(payments.total) as numeric(14, 2))');
    }

    public static function regionKey(int $regionId): string
    {
        return 'region:'.$regionId;
    }

    public static function cityKey(int $cityId): string
    {
        return 'city:'.$cityId;
    }

    private function located(Builder $query, string $entityColumn): Builder
    {
        return $query->join('entities', 'entities.id', '=', $entityColumn);
    }

    private function within(Builder $query, string $column, ReportingPeriod $period): Builder
    {
        return $query->where($column, '>=', $period->start->toIso8601String())
            ->where($column, '<', $period->end->toIso8601String());
    }

    /**
     * @param  literal-string  $aggregate
     * @return array<string, int>
     */
    private function counts(Builder $query, string $aggregate): array
    {
        return array_map(static fn (string $value): int => (int) $value, $this->values($query, $aggregate));
    }

    /**
     * @param  literal-string  $aggregate
     * @return array<string, string>
     */
    private function values(Builder $query, string $aggregate): array
    {
        $totals = [];

        foreach ($query->reorder()->selectRaw(self::LOCATION_SELECT.$aggregate.' as value')->groupByRaw(self::GROUPING)->get() as $row) {
            $key = ScalarValue::int($row->is_region) === 1
                ? self::regionKey(ScalarValue::int($row->region_id))
                : self::cityKey(ScalarValue::int($row->city_id));
            $totals[$key] = ScalarValue::string($row->value);
        }

        return $totals;
    }
}
