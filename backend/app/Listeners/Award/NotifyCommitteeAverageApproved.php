<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CommitteeAverageApproved;
use App\Models\Award\AwardCycle;
use App\Services\Award\AwardStaffNotifier;
use App\Services\Award\CommitteeVisibilityReader;
use App\Services\Entities\EntityNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;
use App\Support\Award\CommitteeEntityPresenter;

final readonly class NotifyCommitteeAverageApproved
{
    public function __construct(private AwardStaffNotifier $notifier, private EntityNotifier $entities, private CommitteeVisibilityReader $visibility) {}

    public function handle(CommitteeAverageApproved $event): void
    {
        $assessment = $event->assessment->loadMissing(['entity', 'committeeAverage']);
        $cycle = AwardCycle::query()->findOrFail($assessment->award_cycle_id);
        $staff = AwardNotices::for(NotificationCode::CommitteeAverageApproved, 'staff', $cycle, AwardLinks::secretaryRequest($cycle->id, $assessment->id), [
            'number' => $assessment->request_number,
            'entity' => $assessment->entity->name->toLocalizedText(),
            'average' => $assessment->committeeAverage?->percent->toDisplay() ?? '-',
        ]);

        $this->entities->toRole(RoleKey::SystemAdmin, $staff, $event->actorId);
        $this->notifier->committeeOf($assessment->id, AwardNotices::for(NotificationCode::CommitteeAverageApproved, 'assessor', $cycle, AwardLinks::committeeWork(), [
            'label' => CommitteeEntityPresenter::label($assessment, $this->visibility->read()),
        ]), $event->actorId);
    }
}
