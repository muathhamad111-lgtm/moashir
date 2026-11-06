<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\PathCount;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssignedRequestsTab;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Entities\EntityFreezeGuard;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class RequestPathCounts
{
    public function __construct(private RequestQueueQuery $queue, private EntityFreezeGuard $freezeGuard) {}

    /**
     * @param  list<AssessmentPath>  $paths
     * @return list<PathCount>
     */
    public function forQueue(array $paths): array
    {
        $byStatus = $this->countByPathAndStatus($this->queue->queued($paths));
        $overdue = $this->countByPath($this->queue->overdue($this->queue->queued($paths)));

        return array_map(static fn (AssessmentPath $path): PathCount => new PathCount($path, [
            'total' => array_sum($byStatus[$path->value] ?? []),
            'awaiting_assignment' => $byStatus[$path->value][$path->reviewStages()->awaitingAssignment->value] ?? 0,
            'overdue' => $overdue[$path->value] ?? 0,
        ]), $paths);
    }

    /**
     * @param  list<AssessmentPath>  $qualifiedPaths
     * @return list<PathCount>
     */
    public function forAssessor(User $assessor, array $qualifiedPaths): array
    {
        $byStatus = $this->countByPathAndStatus($this->freezeGuard->excludeFrozen(
            Assessment::query()->whereIn('path', AssessmentPath::reviewed())->reviewedBy($assessor->id),
        ));
        $paths = array_values(array_filter(
            AssessmentPath::reviewed(),
            static fn (AssessmentPath $path): bool => in_array($path, $qualifiedPaths, true) || isset($byStatus[$path->value]),
        ));

        return array_map(static fn (AssessmentPath $path): PathCount => new PathCount($path, self::tabCounts($byStatus[$path->value] ?? [])), $paths);
    }

    /**
     * @param  array<string, int>  $statuses
     * @return array<string, int>
     */
    private static function tabCounts(array $statuses): array
    {
        $counts = [];

        foreach (AssignedRequestsTab::cases() as $tab) {
            $counts[$tab->value] = array_sum(array_intersect_key($statuses, array_flip($tab->statusValues())));
        }

        return $counts;
    }

    /**
     * @param  Builder<Assessment>  $query
     * @return array<string, array<string, int>>
     */
    private function countByPathAndStatus(Builder $query): array
    {
        $counts = [];

        foreach ($query->toBase()->selectRaw('path, status, count(*) as aggregate')->groupBy('path', 'status')->get() as $row) {
            $values = get_object_vars($row);
            $counts[ScalarValue::string($values['path'] ?? null)][ScalarValue::string($values['status'] ?? null)] = ScalarValue::int($values['aggregate'] ?? null);
        }

        return $counts;
    }

    /**
     * @param  Builder<Assessment>  $query
     * @return array<string, int>
     */
    private function countByPath(Builder $query): array
    {
        $counts = [];

        foreach ($query->toBase()->selectRaw('path, count(*) as aggregate')->groupBy('path')->get() as $row) {
            $values = get_object_vars($row);
            $counts[ScalarValue::string($values['path'] ?? null)] = ScalarValue::int($values['aggregate'] ?? null);
        }

        return $counts;
    }
}
