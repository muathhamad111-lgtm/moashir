<?php

declare(strict_types=1);

namespace App\Services\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Exceptions\Award\AwardRejected;
use App\Models\Award\AwardCycle;
use App\Models\Criteria\CriteriaVersion;
use App\Services\Audit\AuditRecorder;

final readonly class CycleCriteriaVersionFollower
{
    public function __construct(private AuditRecorder $audit) {}

    public function published(): CriteriaVersion
    {
        return CriteriaVersion::query()->published()->first() ?? throw AwardRejected::noPublishedCriteria();
    }

    public function pendingFor(AwardCycle $cycle): ?CriteriaVersion
    {
        if ($cycle->status !== AwardCycleStatus::Draft) {
            return null;
        }

        $published = CriteriaVersion::query()->published()->first();

        return $published instanceof CriteriaVersion && $published->id !== $cycle->criteria_version_id ? $published : null;
    }

    public function bindBeforeOpening(AwardCycle $draft): void
    {
        $published = $this->published();

        if ($published->id === $draft->criteria_version_id) {
            return;
        }

        $before = ['criteria_version_id' => $draft->criteria_version_id];
        $draft->forceFill(['criteria_version_id' => $published->id, 'scale_kind' => null]);
        $this->audit->record(new AuditEntry(AuditEvent::CycleCriteriaVersionMoved, $draft, $before, [
            'criteria_version_id' => $published->id,
            'trigger' => 'open',
        ]));
    }
}
