<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CommitteeDiscussionEntryRecorded;
use App\Events\Award\CommitteeDiscussionOpened;
use App\Models\Award\AwardCycle;
use App\Services\Award\AwardStaffNotifier;
use App\Services\Award\CommitteeVisibilityReader;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;
use App\Support\Award\CommitteeEntityPresenter;

final readonly class NotifyCommitteeDiscussion
{
    public function __construct(private AwardStaffNotifier $notifier, private CommitteeVisibilityReader $visibility) {}

    public function handle(CommitteeDiscussionOpened|CommitteeDiscussionEntryRecorded $event): void
    {
        $discussion = $event->discussion->loadMissing('assessment.entity');
        $cycle = AwardCycle::query()->findOrFail($discussion->award_cycle_id);
        $assessment = $discussion->assessment;

        if ($event instanceof CommitteeDiscussionOpened) {
            $this->notifier->committeeOf($assessment->id, AwardNotices::for(NotificationCode::CommitteeDiscussionOpened, $discussion->mode->value, $cycle, AwardLinks::committeeDiscussions(), [
                'label' => CommitteeEntityPresenter::label($assessment, $this->visibility->read()),
                'deadline' => $discussion->deadline_at === null ? '-' : AwardNotices::moment($discussion->deadline_at),
            ]), $event->actorId);

            return;
        }

        $this->notifier->secretaries($cycle->id, AwardNotices::for(NotificationCode::CommitteeDiscussionEntry, $event->entry->kind->value, $cycle, AwardLinks::secretaryRequest($cycle->id, $assessment->id), [
            'number' => $assessment->request_number,
            'entity' => $assessment->entity->name->toLocalizedText(),
        ])->inAppOnly(), null);
    }
}
