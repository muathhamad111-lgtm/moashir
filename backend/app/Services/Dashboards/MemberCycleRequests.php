<?php

declare(strict_types=1);

namespace App\Services\Dashboards;

use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardCycleMember;
use App\Models\Identity\User;
use App\Support\ScalarValue;
use Illuminate\Database\Eloquent\Builder;

final readonly class MemberCycleRequests
{
    public function __construct(private AssessmentTotals $assessments) {}

    /**
     * @return Builder<Assessment>
     */
    public function of(User $user, CycleMemberRole $role): Builder
    {
        return $this->assessments->operational()
            ->where('assessments.path', AssessmentPath::Advanced)
            ->whereIn('assessments.award_cycle_id', $this->cycleIds($user, $role))
            ->whereIn('assessments.status', Advanced::valuesOf(...$this->visibleStates($role)));
    }

    /**
     * @return list<Advanced>
     */
    private function visibleStates(CycleMemberRole $role): array
    {
        return match ($role) {
            CycleMemberRole::AwardSecretary => Advanced::queueStates(),
            CycleMemberRole::FilteringOfficer => Advanced::filteringVisibleStates(),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function cycles(User $user, CycleMemberRole $role): array
    {
        $counts = $this->of($user, $role)->toBase()
            ->selectRaw('award_cycle_id, count(*) as total')
            ->groupBy('award_cycle_id')
            ->pluck('total', 'award_cycle_id');

        return array_values(AwardCycle::query()
            ->whereIn('id', $this->cycleIds($user, $role))
            ->where('status', '<>', AwardCycleStatus::Closed)
            ->orderByDesc('id')
            ->get(['id', 'name', 'status', 'submission_end', 'committee_start', 'committee_end'])
            ->map(static fn (AwardCycle $cycle): array => [
                'cycle_id' => $cycle->id,
                'name' => $cycle->name->toArray(),
                'status' => $cycle->status->value,
                'submission_end' => $cycle->submission_end->toIso8601String(),
                'committee_start' => $cycle->committee_start->toIso8601String(),
                'committee_end' => $cycle->committee_end->toIso8601String(),
                'requests' => ScalarValue::int($counts->get($cycle->id)),
            ])
            ->all());
    }

    /**
     * @return Builder<AwardCycleMember>
     */
    public function cycleIds(User $user, CycleMemberRole $role): Builder
    {
        return AwardCycleMember::query()->select('award_cycle_id')->where('user_id', $user->id)->where('role', $role);
    }
}
