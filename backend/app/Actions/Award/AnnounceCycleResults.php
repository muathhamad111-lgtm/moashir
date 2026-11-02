<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\SecretaryDecisionDraft;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\SecretaryDecisionKind;
use App\Events\Award\CycleResultsAnnounced;
use App\Exceptions\IllegalStateTransition;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\SecretaryDecisionRecorder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class AnnounceCycleResults
{
    private const string VERB = 'announce';

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $cycleLock,
        private SecretaryDecisionRecorder $decisions,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, ?string $reason): AwardCycle
    {
        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle): AwardCycle {
            $locked = $this->cycleLock->lock($cycle->id);

            if ($locked->status !== AwardCycleStatus::Approved) {
                throw IllegalStateTransition::from($locked->status->value, self::VERB);
            }

            $locked->forceFill(['status' => AwardCycleStatus::Announced, 'announced_at' => Date::now(), 'announced_by_user_id' => $actor->id])->save();
            $decision = $this->decisions->record($actor, new SecretaryDecisionDraft(SecretaryDecisionKind::Announce, $locked->id));
            $this->audit->record(new AuditEntry(AuditEvent::CycleAnnounced, $locked, ['status' => AwardCycleStatus::Approved->value], [
                'status' => AwardCycleStatus::Announced->value,
                'secretary_decision_id' => $decision->id,
            ]));

            CycleResultsAnnounced::dispatch($locked, $actor->id);

            return $locked;
        });
    }
}
