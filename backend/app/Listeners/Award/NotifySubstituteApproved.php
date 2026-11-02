<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CommitteeSubstituteApproved;
use App\Services\Award\AwardStaffNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;

final readonly class NotifySubstituteApproved
{
    public function __construct(private AwardStaffNotifier $notifier) {}

    public function handle(CommitteeSubstituteApproved $event): void
    {
        $substitute = $event->substitute->loadMissing(['awardCycle', 'assessment.entity', 'member.assessor']);
        $notice = AwardNotices::for(NotificationCode::CommitteeSubstituteApproved, 'staff', $substitute->awardCycle, AwardLinks::secretaryRequest($substitute->award_cycle_id, $substitute->assessment_id), [
            'number' => $substitute->assessment->request_number,
            'entity' => $substitute->assessment->entity->name->toLocalizedText(),
            'assessor' => $substitute->member->assessor->name,
        ]);

        $this->notifier->secretariesAndAdmins($substitute->award_cycle_id, $notice->inAppOnly(), $event->actorId);
    }
}
