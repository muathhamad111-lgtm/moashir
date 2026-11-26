<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentAccess;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\PermissionKey;
use App\Enums\Identity\RoleKey;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycleMember;
use App\Models\Identity\User;
use App\Services\Identity\RequestPathScope;
use Illuminate\Database\Eloquent\Builder;

final readonly class AssessmentRecordScope
{
    private const array GLOBAL_READER_ROLES = [RoleKey::SystemAdmin, RoleKey::RequestFollower];

    public function __construct(private RequestPathScope $paths) {}

    public function accessOf(User $user, Assessment $assessment): AssessmentAccess
    {
        if ($assessment->isOwnedBy($user->entity_id)) {
            return AssessmentAccess::Owner;
        }

        if ($this->readsEveryAssessment($user) && $this->paths->covers($user, $assessment->path)) {
            return AssessmentAccess::ReadOnly;
        }

        if ($this->isReviewedBy($user, $assessment)) {
            return AssessmentAccess::Assignee;
        }

        return $this->readsAsCycleMember($user, $assessment) ? AssessmentAccess::CycleMember : AssessmentAccess::None;
    }

    public function readsEveryAssessment(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $user->can(PermissionKey::AssessmentHistoryView->value)) {
            return false;
        }

        $user->loadMissing('roles');

        return array_any(self::GLOBAL_READER_ROLES, static fn (RoleKey $role): bool => in_array($role, $user->roleKeys(), true));
    }

    public function isReviewedBy(User $user, Assessment $assessment): bool
    {
        return $user->can(PermissionKey::RequestsAssignedView->value)
            && Assessment::query()->whereKey($assessment->id)->reviewedBy($user->id)->exists();
    }

    public function readsAsCycleMember(User $user, Assessment $assessment): bool
    {
        if ($assessment->award_cycle_id === null || ! $user->can(PermissionKey::AssessmentHistoryView->value)) {
            return false;
        }

        return Assessment::query()->whereKey($assessment->id)->where(fn (Builder $query): Builder => $this->visibleToMember($query, $user->id))->exists();
    }

    /**
     * @param  Builder<Assessment>  $query
     */
    public function restrict(Builder $query, User $user): void
    {
        if ($this->readsEveryAssessment($user)) {
            $this->restrictToFollowedPaths($query, $user);

            return;
        }

        if ($user->entity_id !== null) {
            $query->ownedBy($user->entity_id);

            return;
        }

        $query->where(fn (Builder $visible): Builder => $this->ownWork($visible, $user));
    }

    /**
     * @param  Builder<Assessment>  $query
     */
    private function restrictToFollowedPaths(Builder $query, User $user): void
    {
        $excluded = $this->paths->excludedPathsFor($user);

        if ($excluded === []) {
            return;
        }

        $query->where(fn (Builder $visible): Builder => $visible
            ->whereNotIn('path', array_map(static fn (AssessmentPath $path): string => $path->value, $excluded))
            ->orWhere(fn (Builder $own): Builder => $this->ownWork($own, $user)));
    }

    /**
     * @param  Builder<Assessment>  $visible
     * @return Builder<Assessment>
     */
    private function ownWork(Builder $visible, User $user): Builder
    {
        $reviews = $user->can(PermissionKey::RequestsAssignedView->value);
        $members = $user->can(PermissionKey::AssessmentHistoryView->value);

        return $visible
            ->when($reviews, static fn (Builder $reviewed): Builder => $reviewed->orWhere(static fn (Builder $inner): Builder => $inner->reviewedBy($user->id)))
            ->when($members, fn (Builder $member): Builder => $member->orWhere(fn (Builder $inner): Builder => $this->visibleToMember($inner, $user->id)))
            ->when(! $reviews && ! $members, static fn (Builder $none): Builder => $none->whereRaw('false'));
    }

    /**
     * @param  Builder<Assessment>  $query
     * @return Builder<Assessment>
     */
    private function visibleToMember(Builder $query, int $userId): Builder
    {
        return $query->where('path', AssessmentPath::Advanced)->where(static fn (Builder $roles): Builder => $roles
            ->where(static fn (Builder $secretary): Builder => $secretary
                ->whereIn('award_cycle_id', self::cyclesOf($userId, CycleMemberRole::AwardSecretary))
                ->whereIn('status', AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::queueStates())))
            ->orWhere(static fn (Builder $filtering): Builder => $filtering
                ->whereIn('award_cycle_id', self::cyclesOf($userId, CycleMemberRole::FilteringOfficer))
                ->whereIn('status', AdvancedAssessmentStatus::valuesOf(...AdvancedAssessmentStatus::filteringVisibleStates()))));
    }

    /**
     * @return Builder<AwardCycleMember>
     */
    private static function cyclesOf(int $userId, CycleMemberRole $role): Builder
    {
        return AwardCycleMember::query()->select('award_cycle_id')->where('user_id', $userId)->where('role', $role);
    }
}
