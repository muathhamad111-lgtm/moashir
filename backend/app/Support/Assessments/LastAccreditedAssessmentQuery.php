<?php

declare(strict_types=1);

namespace App\Support\Assessments;

use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Models\Entities\Entity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * The entity's latest completed ACCREDITED assessment (the self-assessment never counts): its relation for eager
 * loading and the list sorts by its completion date or its published percent, entities never assessed last.
 */
final class LastAccreditedAssessmentQuery
{
    public const string RELATION = 'lastAccreditedAssessment:id,entity_id,completed_at,current_result_snapshot_id';

    public const string RESULT_RELATION = 'lastAccreditedAssessment.currentResult:id,assessment_id,percent';

    public const array SORTS = ['last_assessed_at', 'last_assessment_percent'];

    private const string SORT_VALUE = 'last_accredited_sort_value';

    /**
     * @param  Builder<Assessment>  $assessments
     */
    public static function constrain(Builder $assessments): void
    {
        $assessments->where('assessments.path', AssessmentPath::Accredited->value)
            ->where('assessments.status', AccreditedAssessmentStatus::Completed->value)
            ->whereNotNull('assessments.completed_at');
    }

    /**
     * @param  Builder<Entity>  $query
     */
    public static function orderBy(Builder $query, string $sort, string $direction): void
    {
        $query->addSelect([self::SORT_VALUE => self::latest($sort === 'last_assessed_at' ? 'assessments.completed_at' : 'snapshots.percent')]);
        $query->orderByRaw($direction === 'desc' ? self::SORT_VALUE.' desc nulls last' : self::SORT_VALUE.' asc nulls last');
    }

    private static function latest(string $column): QueryBuilder
    {
        $assessments = Assessment::query()
            ->leftJoin('assessment_result_snapshots as snapshots', 'snapshots.id', '=', 'assessments.current_result_snapshot_id')
            ->whereColumn('assessments.entity_id', 'entities.id')
            ->orderByDesc('assessments.completed_at')
            ->orderByDesc('assessments.id')
            ->limit(1)
            ->select($column);

        self::constrain($assessments);

        return $assessments->toBase();
    }
}
