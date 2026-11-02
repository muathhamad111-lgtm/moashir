<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CommitteeAverageReady;
use App\Models\Award\AwardCycle;
use App\Services\Award\AwardStaffNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;

final readonly class NotifyCommitteeAverageReady
{
    public function __construct(private AwardStaffNotifier $notifier) {}

    public function handle(CommitteeAverageReady $event): void
    {
        $assessment = $event->assessment->loadMissing(['entity', 'committeeAverage']);
        $cycle = AwardCycle::query()->findOrFail($assessment->award_cycle_id);
        $notice = AwardNotices::for(NotificationCode::CommitteeAllSubmitted, 'staff', $cycle, AwardLinks::secretaryRequest($cycle->id, $assessment->id), [
            'number' => $assessment->request_number,
            'entity' => $assessment->entity->name->toLocalizedText(),
            'average' => $assessment->committeeAverage?->percent->toDisplay() ?? '-',
        ]);

        $this->notifier->secretaries($cycle->id, $notice, $event->actorId);
    }
}
