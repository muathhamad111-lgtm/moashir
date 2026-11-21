<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CommitteeRequestsDistributed;
use App\Models\Award\AwardCycle;
use App\Services\Award\AwardStaffNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;
use App\Support\ValueObjects\LocalizedText;

final readonly class NotifyCommitteeAssigned
{
    public function __construct(private AwardStaffNotifier $notifier) {}

    public function handle(CommitteeRequestsDistributed $event): void
    {
        $cycle = AwardCycle::query()->findOrFail($event->distribution->cycleId);

        foreach ($event->distribution->countsByUser as $userId => $count) {
            $this->notifier->assessor($userId, AwardNotices::for(NotificationCode::CommitteeAssigned, 'assessor', $cycle, AwardLinks::committeeWork(), ['entities' => LocalizedText::fromChoice('quantities.entities', $count)]));
        }
    }
}
