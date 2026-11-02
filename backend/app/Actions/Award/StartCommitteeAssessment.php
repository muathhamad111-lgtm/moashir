<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Events\Award\CommitteeRequestsDistributed;
use App\Events\Award\CycleProgressChanged;
use App\Exceptions\Award\CommitteeRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Award\AwardCycle;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\CommitteeCycleDistributor;
use App\Services\Award\CommitteeRoster;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class StartCommitteeAssessment
{
    private const string VERB = 'start_committee';

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $rowLock,
        private CommitteeRoster $roster,
        private CommitteeCycleDistributor $distributor,
        private AuditRecorder $audit,
    ) {}

    public function handle(?User $actor, AwardCycle $cycle, ?string $reason): AwardCycle
    {
        if ($actor instanceof User) {
            $this->reason->record($actor, $reason);
        }

        return DB::transaction(function () use ($actor, $cycle): AwardCycle {
            $locked = $this->rowLock->lock($cycle->id);

            if ($locked->status !== AwardCycleStatus::SubmissionClosed) {
                throw IllegalStateTransition::from($locked->status->value, self::VERB);
            }

            if ($this->roster->activeMembers($locked->id)->isEmpty()) {
                throw CommitteeRejected::noneActive();
            }

            $now = Date::now();
            $previousStart = $locked->committee_start->toIso8601String();

            if ($locked->committee_start->greaterThan($now)) {
                $locked->committee_start = $now->copy()->max($locked->submission_end);
            }

            $locked->forceFill(['status' => AwardCycleStatus::CommitteeAssessment, 'committee_started_at' => $now])->save();
            $distribution = $this->distributor->distributeAll($locked, $actor?->id);
            $this->audit->record(new AuditEntry(AuditEvent::CycleCommitteeStarted, $locked, ['status' => AwardCycleStatus::SubmissionClosed->value, 'committee_start' => $previousStart], [
                'status' => AwardCycleStatus::CommitteeAssessment->value,
                'committee_start' => $locked->committee_start->toIso8601String(),
                'distributed_assessors' => count($distribution->countsByUser),
                'trigger' => $actor instanceof User ? 'manual' : 'schedule',
            ]));

            if (! $distribution->isEmpty()) {
                CommitteeRequestsDistributed::dispatch($distribution, $actor?->id);
            }

            CycleProgressChanged::dispatch($locked->id);

            return $locked;
        });
    }
}
