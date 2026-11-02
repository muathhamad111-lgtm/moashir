<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\CycleMemberChanges;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CandidateRole;
use App\Enums\Award\CycleMemberRole;
use App\Exceptions\Award\AwardRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardCycleMember;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\CycleMembership;
use App\Services\Award\CycleSegregation;
use App\Services\Award\CycleStaffEligibility;
use Illuminate\Support\Facades\DB;

final readonly class SyncCycleMembers
{
    private const string VERB = 'update_members';

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $rowLock,
        private CycleStaffEligibility $eligibility,
        private CycleSegregation $segregation,
        private CycleMembership $membership,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, CycleMemberChanges $changes, ?string $reason): AwardCycle
    {
        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle, $changes): AwardCycle {
            $locked = $this->rowLock->lock($cycle->id);

            if (! $locked->status->acceptsRosterChanges()) {
                throw IllegalStateTransition::from($locked->status->value, self::VERB);
            }

            $diff = [];

            foreach ($changes->roles() as $role) {
                $diff[$role->value] = $this->syncRole($actor, $locked, $role, $changes->userIdsFor($role));
            }

            $this->audit->record(new AuditEntry(AuditEvent::CycleMembersChanged, $locked, [], $diff));

            return $locked;
        });
    }

    /**
     * @param  list<int>  $userIds
     * @return array{added: list<int>, removed: list<int>}
     */
    private function syncRole(User $actor, AwardCycle $cycle, CycleMemberRole $role, array $userIds): array
    {
        $current = $this->membership->memberIdsOf($cycle->id, $role);
        $added = array_values(array_diff($userIds, $current));
        $removed = array_values(array_diff($current, $userIds));

        foreach ($added as $userId) {
            $field = $role->value.'_ids.'.(int) array_search($userId, $userIds, true);
            $this->eligibility->find(CandidateRole::from($role->value), $userId) ?? throw AwardRejected::memberInvalid($field);
            $this->segregation->assertMayJoinAsMember($cycle->id, $userId, $field);
            AwardCycleMember::query()->create(['award_cycle_id' => $cycle->id, 'user_id' => $userId, 'role' => $role, 'added_by_user_id' => $actor->id]);
        }

        AwardCycleMember::query()->where('award_cycle_id', $cycle->id)->where('role', $role)->whereIn('user_id', $removed)->delete();

        return ['added' => $added, 'removed' => $removed];
    }
}
