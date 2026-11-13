<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentListFilters;
use App\Data\Assessments\AssessmentRowsPage;
use App\Data\Assessments\MissedPeriodRow;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentListQuery;
use App\Services\Periods\MissedPeriodTimeline;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAssessmentRows
{
    private const array TIMELINE_SORTS = ['created_at', '-created_at'];

    public function __construct(
        private ListAssessments $assessments,
        private AssessmentListQuery $listQuery,
        private MissedPeriodTimeline $timeline,
    ) {}

    public function handle(User $actor, AssessmentListFilters $filters): AssessmentRowsPage
    {
        $entityId = $this->timelineEntityOf($actor, $filters);

        if ($entityId === null) {
            $page = $this->assessments->handle($actor, $filters);

            return new AssessmentRowsPage(new LengthAwarePaginator($this->rowsOf(array_values($page->items())), $page->total(), $page->perPage(), $page->currentPage()), 0);
        }

        return $this->timeline->page($this->listQuery->scoped($actor, $filters), $entityId, $filters, ListAssessments::RELATIONS);
    }

    /**
     * @param  list<Assessment>  $assessments
     * @return list<Assessment|MissedPeriodRow>
     */
    private function rowsOf(array $assessments): array
    {
        return $assessments;
    }

    private function timelineEntityOf(User $actor, AssessmentListFilters $filters): ?int
    {
        $plain = $filters->path === AssessmentPath::Accredited
            && $filters->status === null
            && $filters->isOpen === null
            && $filters->hasResult === null
            && in_array($filters->sort, self::TIMELINE_SORTS, true);

        if (! $plain) {
            return null;
        }

        if ($actor->entity_id !== null) {
            return $actor->entity_id;
        }

        return $actor->can(PermissionKey::RequestsQueueView->value) ? $filters->entityId : null;
    }
}
