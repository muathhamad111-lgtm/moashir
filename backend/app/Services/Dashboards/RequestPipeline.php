<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssignmentStatus;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentAssignment;
use App\Support\Assessments\PathPresenter;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class RequestPipeline
{
    public function __construct(private AssessmentTotals $assessments) {}

    /**
     * @param  list<AssessmentPath>  $followed
     * @return Builder<Assessment>
     */
    public function queue(array $followed): Builder
    {
        return $this->assessments->operational()->where(static function (Builder $paths) use ($followed): void {
            foreach ($followed as $path) {
                $paths->orWhere(static fn (Builder $inPath): Builder => $inPath->where('path', $path)->whereIn('status', $path->queueStateValues()));
            }

            if ($followed === []) {
                $paths->whereRaw('false');
            }
        });
    }

    /**
     * @param  list<AssessmentPath>  $followed
     * @return list<array{path: string, path_label: array{ar: string, en: string}|null, status: string, count: int}>
     */
    public function byStatus(array $followed): array
    {
        return array_values($this->queue($followed)->toBase()
            ->selectRaw('path, status, count(*) as total')
            ->groupBy('path', 'status')
            ->orderBy('path')
            ->orderBy('status')
            ->get()
            ->map(static function (object $row): array {
                $values = get_object_vars($row);

                return [
                    ...PathPresenter::ofValue(ScalarValue::string($values['path'] ?? null)),
                    'status' => ScalarValue::string($values['status'] ?? null),
                    'count' => ScalarValue::int($values['total'] ?? null),
                ];
            })
            ->all());
    }

    /**
     * @param  list<AssessmentPath>  $followed
     * @return list<array{assessor_id: int, name: string, active: int, completed: int}>
     */
    public function busiestAssessors(array $followed, int $limit): array
    {
        return array_values(AssessmentAssignment::query()
            ->toBase()
            ->join('users', 'users.id', '=', 'assessment_assignments.assessor_user_id')
            ->whereIn('assessment_assignments.assessment_id', $this->queue($followed)->select('assessments.id'))
            ->selectRaw('users.id as assessor_id, users.name as name')
            ->selectRaw('count(*) filter (where assessment_assignments.status = ?) as active', [AssignmentStatus::Active->value])
            ->selectRaw('count(*) filter (where assessment_assignments.status = ?) as completed', [AssignmentStatus::Completed->value])
            ->groupBy('users.id', 'users.name')
            ->orderByRaw('count(*) filter (where assessment_assignments.status in (?, ?)) desc', [AssignmentStatus::Active->value, AssignmentStatus::Completed->value])
            ->orderBy('users.id')
            ->limit($limit)
            ->get()
            ->map(static function (object $row): array {
                $values = get_object_vars($row);

                return [
                    'assessor_id' => ScalarValue::int($values['assessor_id'] ?? null),
                    'name' => ScalarValue::string($values['name'] ?? null),
                    'active' => ScalarValue::int($values['active'] ?? null),
                    'completed' => ScalarValue::int($values['completed'] ?? null),
                ];
            })
            ->all());
    }
}
