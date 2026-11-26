<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Assessments\AssignmentStatus;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Services\Dashboards\AssessmentTotals;
use App\Support\Dashboards\PeriodWindow;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;

final readonly class AssessorWorkloadBreakdown implements OverviewBreakdownSource
{
    private const int TOP_ASSESSORS = 8;

    private const string OPEN = 'count(*) filter (where assignments.status = ?)';

    private const string COMPLETED = 'count(*) filter (where assignments.status = ? and assignments.ended_at >= ? and assignments.ended_at < ?)';

    public function __construct(private AssessmentTotals $assessments) {}

    public function keys(): array
    {
        return [OverviewBreakdownKey::AssessorWorkload];
    }

    /**
     * @return array{assessor_workload: list<array{key: string, name: string, open: int, completed: int}>}
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array
    {
        $matching = $this->assessments->operational()->select('assessments.id');
        $filter->applyToAssessments($matching);
        $open = [AssignmentStatus::Active->value];
        $completed = [AssignmentStatus::Completed->value, ...PeriodWindow::bindings($period)];

        $rows = DB::table('assessment_assignments as assignments')
            ->join('users', 'users.id', '=', 'assignments.assessor_user_id')
            ->whereIn('assignments.assessment_id', $matching->toBase())
            ->selectRaw('users.id, users.name, '.self::OPEN.' as open, '.self::COMPLETED.' as completed', [...$open, ...$completed])
            ->groupBy('users.id', 'users.name')
            ->havingRaw('('.self::OPEN.' + '.self::COMPLETED.') > 0', [...$open, ...$completed])
            ->orderByDesc('open')->orderByDesc('completed')->orderBy('users.id')
            ->limit(self::TOP_ASSESSORS)
            ->get();

        return [OverviewBreakdownKey::AssessorWorkload->value => array_values($rows->map(static function (object $row): array {
            $values = get_object_vars($row);

            return [
                'key' => (string) ScalarValue::int($values['id'] ?? null),
                'name' => ScalarValue::string($values['name'] ?? null),
                'open' => ScalarValue::int($values['open'] ?? null),
                'completed' => ScalarValue::int($values['completed'] ?? null),
            ];
        })->all())];
    }
}
