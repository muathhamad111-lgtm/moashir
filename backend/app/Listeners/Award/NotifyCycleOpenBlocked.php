<?php

declare(strict_types=1);

namespace App\Listeners\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Events\Award\CycleOpenBlocked;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CycleMembership;
use App\Services\Entities\EntityNotifier;
use App\Support\Award\AwardLinks;
use App\Support\Award\AwardNotices;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;

/**
 * Alerts the System Administrator and the cycle's Award Secretaries (CYC-03) that the scheduled opening is waiting
 * for initial assessors, and audits the skipped opening. The schedule retries every minute, so both happen at most
 * once a day per cycle.
 */
final readonly class NotifyCycleOpenBlocked
{
    public function __construct(private EntityNotifier $notifier, private CycleMembership $membership, private AuditRecorder $audit) {}

    public function handle(CycleOpenBlocked $event): void
    {
        $cycle = $event->cycle;

        if (! Cache::add('award:cycle-open-blocked:'.$cycle->id, true, Date::now()->addDay())) {
            return;
        }

        $this->audit->record(new AuditEntry(AuditEvent::CycleOpenBlocked, $cycle, [], [
            'status' => $cycle->status->value,
            'reason' => 'cycle.no_initial_assessors',
            'trigger' => 'schedule',
        ]));

        $notice = AwardNotices::for(NotificationCode::CycleOpenBlocked, AwardNotices::FOR_STAFF, $cycle, AwardLinks::cycle($cycle->id))->inAppOnly();

        $this->notifier->toRole(RoleKey::SystemAdmin, $notice, null);
        $this->notifier->toStaffUsers($this->membership->memberIdsOf($cycle->id, CycleMemberRole::AwardSecretary), $notice, null);
    }
}
