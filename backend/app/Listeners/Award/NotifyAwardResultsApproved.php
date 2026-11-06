<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Enums\Notifications\NotificationCode;
use App\Events\Award\NationalResultsApproved;
use App\Events\Award\RegionResultsApproved;
use App\Services\Award\AwardStaffNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;
use App\Support\ValueObjects\LocalizedText;

final readonly class NotifyAwardResultsApproved
{
    public function __construct(private AwardStaffNotifier $notifier) {}

    public function handle(RegionResultsApproved|NationalResultsApproved $event): void
    {
        $cycle = $event->cycle;
        $link = AwardLinks::results($cycle->id);

        if ($event instanceof NationalResultsApproved) {
            $this->notifier->secretariesAndAdmins($cycle->id, AwardNotices::for(NotificationCode::NationalResultApproved, 'staff', $cycle, $link)->inAppOnly(), $event->actorId);

            return;
        }

        $extra = ['region' => $event->region->name, 'winners' => LocalizedText::fromChoice('quantities.winners', $event->winnersCount)];
        $this->notifier->secretariesAndAdmins($cycle->id, AwardNotices::for(NotificationCode::RegionApproved, 'staff', $cycle, $link, $extra)->inAppOnly(), $event->actorId);
        $this->notifier->secretariesAndAdmins($cycle->id, AwardNotices::for(NotificationCode::QualifiedForNational, 'staff', $cycle, $link, $extra)->inAppOnly(), $event->actorId);
    }
}
