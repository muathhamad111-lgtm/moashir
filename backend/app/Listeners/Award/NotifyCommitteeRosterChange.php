<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CommitteeAssessorDisabled;
use App\Events\Award\CommitteeAssessorSubstituted;
use App\Services\Award\AwardStaffNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;

final readonly class NotifyCommitteeRosterChange
{
    public function __construct(private AwardStaffNotifier $notifier) {}

    public function handle(CommitteeAssessorDisabled|CommitteeAssessorSubstituted $event): void
    {
        if ($event instanceof CommitteeAssessorDisabled) {
            $member = $event->member->loadMissing(['awardCycle', 'assessor']);
            $notice = AwardNotices::for(NotificationCode::CommitteeAssessorDisabled, 'staff', $member->awardCycle, AwardLinks::committee($member->award_cycle_id), ['assessor' => $member->assessor->name]);
            $this->notifier->secretariesAndAdmins($member->award_cycle_id, $notice->inAppOnly(), $event->actorId);

            return;
        }

        $substitute = $event->substitute->loadMissing(['awardCycle', 'assessor']);
        $replaced = $event->replaced->loadMissing('assessor');
        $cycle = $substitute->awardCycle;

        $this->notifier->assessor($substitute->assessor_user_id, AwardNotices::for(NotificationCode::CommitteeSubstituteAssigned, 'assessor', $cycle, AwardLinks::committeeWork()));
        $this->notifier->secretaries($cycle->id, AwardNotices::for(NotificationCode::CommitteeSubstituteAssigned, 'staff', $cycle, AwardLinks::committee($cycle->id), [
            'assessor' => $substitute->assessor->name,
            'replaced' => $replaced->assessor->name,
        ]), $event->actorId);
    }
}
