<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Data\Entities\EntityNotice;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\ReturnedToCommittee;
use App\Models\Award\AwardCycle;
use App\Services\Award\AwardStaffNotifier;
use App\Services\Award\CommitteeVisibilityReader;
use App\Support\Award\AwardNotices;
use App\Support\Award\CommitteeEntityPresenter;
use App\Support\Award\CommitteeWorkLinks;

final readonly class NotifyReturnedToCommittee
{
    public function __construct(private AwardStaffNotifier $notifier, private CommitteeVisibilityReader $visibility) {}

    public function handle(ReturnedToCommittee $event): void
    {
        $assessment = $event->assessment->loadMissing('entity');
        $cycle = AwardCycle::query()->findOrFail($assessment->award_cycle_id);

        $extra = [
            'label' => CommitteeEntityPresenter::label($assessment, $this->visibility->read()),
            'note' => (string) $event->decision->note,
        ];

        // Each assessor's notice opens their own returned evaluation, not the list (award UX phase 1).
        $this->notifier->eachEvaluationOf(
            $assessment->id,
            static fn (int $assignmentId): EntityNotice => AwardNotices::for(NotificationCode::ReturnedToCommittee, 'assessor', $cycle, CommitteeWorkLinks::evaluation($assignmentId), $extra),
            $event->actorId,
        );
    }
}
