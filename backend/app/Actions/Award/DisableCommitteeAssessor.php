<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CommitteeMembershipStatus;
use App\Events\Award\CommitteeAssessorDisabled;
use App\Models\Award\AwardCycle;
use App\Models\Award\CycleCommitteeAssessor;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CommitteeRosterGate;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class DisableCommitteeAssessor
{
    private const string VERB = 'disable_committee_assessor';

    public function __construct(private CommitteeRosterGate $gate, private AuditRecorder $audit) {}

    public function handle(User $actor, AwardCycle $cycle, CycleCommitteeAssessor $member, string $disableReason, ?string $reason): CycleCommitteeAssessor
    {
        return DB::transaction(function () use ($actor, $cycle, $member, $disableReason, $reason): CycleCommitteeAssessor {
            $locked = $this->gate->enter($actor, $cycle, self::VERB, $reason);
            $seat = $this->gate->member($member, CommitteeMembershipStatus::Active, self::VERB);
            $seat->forceFill([
                'status' => CommitteeMembershipStatus::Disabled,
                'disabled_at' => Date::now(),
                'disabled_reason' => $disableReason,
                'disabled_by_user_id' => $actor->id,
            ])->save();
            $this->audit->record(new AuditEntry(AuditEvent::CommitteeAssessorDisabled, $locked, ['member_id' => $seat->id, 'status' => CommitteeMembershipStatus::Active->value], [
                'status' => CommitteeMembershipStatus::Disabled->value,
                'assessor_user_id' => $seat->assessor_user_id,
                'disabled_reason' => $disableReason,
            ]));

            CommitteeAssessorDisabled::dispatch($seat, $actor->id);

            return $seat;
        });
    }
}
