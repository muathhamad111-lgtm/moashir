<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssignedRequestsTab;
use App\Models\Assessments\Assessment;
use App\Models\Identity\User;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Pagination\LengthAwarePaginator;

final readonly class ListAssignedRequests
{
    public const array RELATIONS = ['entity.region', 'currentResult', 'openCompletionRequest', 'awardCycle'];

    public function __construct(private EntityFreezeGuard $freezeGuard) {}

    /**
     * @return LengthAwarePaginator<int, Assessment>
     */
    public function handle(User $assessor, AssessmentPath $path, ?AssignedRequestsTab $tab, int $perPage): LengthAwarePaginator
    {
        $query = $this->freezeGuard->excludeFrozen(Assessment::query()
            ->with([...self::RELATIONS, 'assignments' => static fn (HasMany $assignments): HasMany => $assignments->where('assessor_user_id', $assessor->id)])
            ->ofPath($path)
            ->reviewedBy($assessor->id));

        if ($tab instanceof AssignedRequestsTab) {
            $query->whereIn('status', $tab->statusValues());
        }

        return $query->orderByRaw('due_at asc nulls last')->orderByDesc('id')->paginate($perPage);
    }
}
