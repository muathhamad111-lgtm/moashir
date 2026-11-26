<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Data\Assessments\AssessmentListFilters;
use App\Data\Assessments\AssessmentRowsPage;
use App\Data\Assessments\MissedPeriodRow;
use App\Enums\Periods\EntityPeriodState;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

final class MissedPeriodTimeline
{
    private const string REQUEST = 'request';

    private const string MISSED = 'missed_period';

    /**
     * @param  Builder<Assessment>  $requests
     * @param  list<string>  $relations
     */
    public function page(Builder $requests, int $entityId, AssessmentListFilters $filters, array $relations): AssessmentRowsPage
    {
        $direction = str_starts_with($filters->sort, '-') ? 'desc' : 'asc';
        $rows = DB::query()
            ->fromSub($this->requestRows($requests)->unionAll($this->missedRows($entityId)), 'timeline')
            ->orderBy('sort_at', $direction)
            ->orderBy('row_key', $direction)
            ->paginate($filters->perPage);

        $items = $this->hydrate(array_values($rows->items()), $relations);

        return new AssessmentRowsPage($rows->setCollection(collect($items)), $this->missedRows($entityId)->count());
    }

    /**
     * @param  Builder<Assessment>  $requests
     */
    private function requestRows(Builder $requests): QueryBuilder
    {
        return $requests->toBase()->select([
            DB::raw("'".self::REQUEST."' as row_type"),
            'assessments.id as row_id',
            'assessments.created_at as sort_at',
            DB::raw("'".self::REQUEST."-' || assessments.id as row_key"),
        ]);
    }

    private function missedRows(int $entityId): QueryBuilder
    {
        return DB::table('entity_period_statuses')
            ->join('assessment_periods', 'assessment_periods.id', '=', 'entity_period_statuses.assessment_period_id')
            ->where('entity_period_statuses.entity_id', $entityId)
            ->where('entity_period_statuses.status', EntityPeriodState::Missed->value)
            ->select([
                DB::raw("'".self::MISSED."' as row_type"),
                'assessment_periods.id as row_id',
                'assessment_periods.ends_at as sort_at',
                DB::raw("'period-' || assessment_periods.id as row_key"),
            ]);
    }

    /**
     * @param  list<mixed>  $rows
     * @param  list<string>  $relations
     * @return list<Assessment|MissedPeriodRow>
     */
    private function hydrate(array $rows, array $relations): array
    {
        $requestIds = $this->idsOf($rows, self::REQUEST);
        $periodIds = $this->idsOf($rows, self::MISSED);
        $assessments = $requestIds === [] ? collect() : Assessment::query()->with($relations)->whereKey($requestIds)->get()->keyBy('id');
        $periods = $periodIds === [] ? collect() : AssessmentPeriod::query()->whereKey($periodIds)->get()->keyBy('id');
        $items = [];

        foreach ($rows as $row) {
            $values = is_object($row) ? get_object_vars($row) : [];
            $id = ScalarValue::int($values['row_id'] ?? null);
            $item = ($values['row_type'] ?? null) === self::MISSED ? $periods->get($id) : $assessments->get($id);

            if ($item instanceof AssessmentPeriod) {
                $items[] = new MissedPeriodRow($item);
            } elseif ($item instanceof Assessment) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<int>
     */
    private function idsOf(array $rows, string $type): array
    {
        $ids = [];

        foreach ($rows as $row) {
            $values = is_object($row) ? get_object_vars($row) : [];

            if (($values['row_type'] ?? null) === $type) {
                $ids[] = ScalarValue::int($values['row_id'] ?? null);
            }
        }

        return $ids;
    }
}
