<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Assessments\AssessmentListFilters;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Builder;

final readonly class AssessmentListQuery
{
    public function __construct(private AssessmentRecordScope $scope, private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return Builder<Assessment>
     */
    public function scoped(User $actor, AssessmentListFilters $filters): Builder
    {
        $query = Assessment::query();
        $this->scope->restrict($query, $actor);

        if ($filters->entityId === null) {
            $this->freezeGuard->excludeFrozen($query);
        } else {
            $query->ownedBy($filters->entityId);
        }

        if ($filters->path instanceof AssessmentPath) {
            $query->ofPath($filters->path);
        }

        return $query;
    }
}
