<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CommitteeEvaluationStarted;
use App\Events\Award\CommitteeEvaluationSubmitted;
use App\Services\Award\AwardStaffNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;

final readonly class NotifyCommitteeProgress
{
    public function __construct(private AwardStaffNotifier $notifier) {}

    public function handle(CommitteeEvaluationStarted|CommitteeEvaluationSubmitted $event): void
    {
        $assignment = $event->assignment->loadMissing(['assessment.entity', 'awardCycle', 'member.assessor']);
        $code = $event instanceof CommitteeEvaluationStarted ? NotificationCode::CommitteeEvaluationStarted : NotificationCode::CommitteeEvaluationSubmitted;
        $notice = AwardNotices::for($code, 'staff', $assignment->awardCycle, AwardLinks::secretaryRequest($assignment->award_cycle_id, $assignment->assessment_id), [
            'number' => $assignment->assessment->request_number,
            'entity' => $assignment->assessment->entity->name->toLocalizedText(),
            'assessor' => $assignment->member->assessor->name,
        ]);

        $this->notifier->secretariesAndAdmins($assignment->award_cycle_id, $notice->inAppOnly(), $assignment->member->assessor_user_id);
    }
}
