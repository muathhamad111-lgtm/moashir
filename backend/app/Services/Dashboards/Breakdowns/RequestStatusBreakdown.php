<?php

declare(strict_types=1);

namespace App\Services\Dashboards\Breakdowns;

use App\Contracts\Dashboards\OverviewBreakdownSource;
use App\Data\Dashboards\BoardFilter;
use App\Data\Dashboards\ReportingPeriod;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Dashboards\OverviewBreakdownKey;
use App\Enums\Dashboards\RequestStatusGroup;
use App\Services\Dashboards\AssessmentTotals;
use App\Support\Dashboards\PeriodWindow;
use App\Support\ScalarValue;

final readonly class RequestStatusBreakdown implements OverviewBreakdownSource
{
    public function __construct(private AssessmentTotals $assessments) {}

    public function keys(): array
    {
        return [OverviewBreakdownKey::RequestsByStatus];
    }

    /**
     * @return array{requests_by_status: list<array{key: string, count: int}>}
     */
    public function breakdowns(ReportingPeriod $period, BoardFilter $filter): array
    {
        $counts = array_fill_keys(array_column(RequestStatusGroup::cases(), 'value'), 0);

        foreach ($this->countsByPathAndStatus($period, $filter) as [$path, $status, $count]) {
            $group = RequestStatusGroup::of($path, $status);

            if ($group instanceof RequestStatusGroup) {
                $counts[$group->value] += $count;
            }
        }

        return [OverviewBreakdownKey::RequestsByStatus->value => array_map(
            static fn (string $key, int $count): array => ['key' => $key, 'count' => $count],
            array_keys($counts),
            array_values($counts),
        )];
    }

    /**
     * @return list<array{0: AssessmentPath, 1: string, 2: int}>
     */
    private function countsByPathAndStatus(ReportingPeriod $period, BoardFilter $filter): array
    {
        $created = $this->assessments->operational();
        PeriodWindow::moments($created, 'assessments.created_at', $period);
        $filter->applyToAssessments($created);
        $rows = [];

        foreach ($created->toBase()->reorder()->selectRaw('path, status, count(*) as total')->groupBy(['path', 'status'])->get() as $row) {
            $values = get_object_vars($row);
            $path = AssessmentPath::tryFrom(ScalarValue::string($values['path'] ?? null));

            if ($path instanceof AssessmentPath) {
                $rows[] = [$path, ScalarValue::string($values['status'] ?? null), ScalarValue::int($values['total'] ?? null)];
            }
        }

        return $rows;
    }
}
