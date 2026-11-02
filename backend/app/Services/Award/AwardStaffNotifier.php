<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Entities\EntityNotice;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Entities\EntityNotifier;
use App\Support\ScalarValue;

final readonly class AwardStaffNotifier
{
    public function __construct(private EntityNotifier $notifier, private CycleMembership $membership) {}

    public function secretaries(int $cycleId, EntityNotice $notice, ?int $actorId): void
    {
        $this->notifier->toStaffUsers($this->membership->memberIdsOf($cycleId, CycleMemberRole::AwardSecretary), $notice, $actorId);
    }

    public function secretariesAndAdmins(int $cycleId, EntityNotice $notice, ?int $actorId): void
    {
        $admins = array_map(static fn (mixed $id): int => ScalarValue::int($id), User::query()->role(RoleKey::SystemAdmin->value)->pluck('id')->all());
        $recipients = array_values(array_unique([...$this->membership->memberIdsOf($cycleId, CycleMemberRole::AwardSecretary), ...$admins]));

        $this->notifier->toStaffUsers($recipients, $notice, $actorId);
    }

    public function committeeOf(int $assessmentId, EntityNotice $notice, ?int $actorId): void
    {
        $userIds = CycleCommitteeAssessor::query()
            ->active()
            ->whereIn('id', CommitteeAssignment::query()->select('cycle_committee_assessor_id')->where('assessment_id', $assessmentId)->live())
            ->pluck('assessor_user_id')
            ->map(static fn (mixed $id): int => ScalarValue::int($id))
            ->all();

        $this->notifier->toStaffUsers(array_values($userIds), $notice, $actorId);
    }

    public function assessor(int $userId, EntityNotice $notice): void
    {
        $this->notifier->toStaffUser($userId, $notice);
    }
}
