<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Notifications\NotificationCode;
use App\Events\Award\ReturnedToCommittee;
use App\Models\Award\AwardCycle;
use App\Services\Award\AwardStaffNotifier;
use App\Services\Award\CommitteeVisibilityReader;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;
use App\Support\Award\CommitteeEntityPresenter;

final readonly class NotifyReturnedToCommittee
{
    public function __construct(private AwardStaffNotifier $notifier, private CommitteeVisibilityReader $visibility) {}

    public function handle(ReturnedToCommittee $event): void
    {
        $assessment = $event->assessment->loadMissing('entity');
        $cycle = AwardCycle::query()->findOrFail($assessment->award_cycle_id);

        $this->notifier->committeeOf($assessment->id, AwardNotices::for(NotificationCode::ReturnedToCommittee, 'assessor', $cycle, AwardLinks::committeeWork(), [
            'label' => CommitteeEntityPresenter::label($assessment, $this->visibility->read()),
            'note' => (string) $event->decision->note,
        ]), $event->actorId);
    }
}
