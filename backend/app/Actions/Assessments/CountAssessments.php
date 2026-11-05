<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AssessmentCounts;
use App\Data\Assessments\AssessmentListFilters;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentListQuery;
use App\Support\Assessments\StatusMask;

final readonly class CountAssessments
{
    public function __construct(private AssessmentListQuery $listQuery) {}

    public function handle(User $actor, AssessmentListFilters $filters): AssessmentCounts
    {
        $scoped = $this->listQuery->scoped($actor, $filters);

        return new AssessmentCounts(
            (clone $scoped)->count(),
            (clone $scoped)->openForViewer(StatusMask::hiddenStatusValuesFor($actor))->count(),
            (clone $scoped)->exposingResult()->count(),
        );
    }
}
