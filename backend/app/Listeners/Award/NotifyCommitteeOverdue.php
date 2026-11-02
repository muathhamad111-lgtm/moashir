<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CommitteeAssessorOverdue;
use App\Services\Award\AwardStaffNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;

final readonly class NotifyCommitteeOverdue
{
    public function __construct(private AwardStaffNotifier $notifier) {}

    public function handle(CommitteeAssessorOverdue $event): void
    {
        $member = $event->member->loadMissing(['awardCycle', 'assessor']);
        $cycle = $member->awardCycle;
        $extra = ['count' => (string) $event->overdueCount, 'assessor' => $member->assessor->name, 'committee_end' => AwardNotices::moment($cycle->committee_end)];

        $this->notifier->assessor($member->assessor_user_id, AwardNotices::for(NotificationCode::CommitteeOverdue, 'assessor', $cycle, AwardLinks::committeeWork(), $extra));
        $this->notifier->secretariesAndAdmins($cycle->id, AwardNotices::for(NotificationCode::CommitteeOverdue, 'staff', $cycle, AwardLinks::committee($cycle->id), $extra), $member->assessor_user_id);
    }
}
