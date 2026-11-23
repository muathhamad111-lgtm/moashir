<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\CycleAttributes;
use App\Data\Award\CycleSchedule;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Events\Award\ParticipationCertificatesEnabled;
use App\Exceptions\Award\AwardRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\CycleAuditValues;
use App\Services\Award\CycleFieldLocks;
use App\Services\Award\QualificationThresholdLock;
use App\Support\ValueObjects\Percent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAwardCycle
{
    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $rowLock,
        private CycleFieldLocks $locks,
        private CycleAuditValues $auditValues,
        private AuditRecorder $audit,
        private QualificationThresholdLock $thresholdLock,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, CycleAttributes $changes, ?string $reason): AwardCycle
    {
        if ($changes->isEmpty()) {
            return $cycle->load('regions');
        }

        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle, $changes): AwardCycle {
            $locked = $this->rowLock->lock($cycle->id);
            $fields = $changes->touchedFields();
            $this->locks->assertEditable($actor, $locked->status, $changes);
            $this->assertThresholdOpen($locked, $changes);
            $this->assertSchedule($locked, $changes);
            $this->assertRegionsKeepRequests($locked, $changes->regionIds);
            $before = $this->auditValues->of($locked, $fields);
            $gaveParticipation = $locked->participation_certificate_enabled;

            $this->apply($locked, $changes);
            $this->audit->record(new AuditEntry(AuditEvent::CycleUpdated, $locked, $before, $this->auditValues->of($locked, $fields)));

            if (! $gaveParticipation && $locked->participation_certificate_enabled) {
                ParticipationCertificatesEnabled::dispatch($locked);
            }

            return $locked;
        });
    }

    private function apply(AwardCycle $locked, CycleAttributes $changes): void
    {
        $locked->fill($changes->columnValues());

        if ($changes->criteriaVersionId !== null) {
            if (! CriteriaVersion::query()->published()->whereKey($changes->criteriaVersionId)->exists()) {
                throw AwardRejected::criteriaVersionNotPublished();
            }

            $locked->scale_kind = null;
        }

        $locked->save();

        if ($changes->regionIds !== null) {
            $locked->regions()->sync($changes->regionIds);
            $locked->load('regions');
        }
    }

    /**
     * Runs under the cycle row lock, which a filtering decision also takes: a decision and a threshold change never
     * interleave, so the threshold can never move once a decision is recorded. Re-sending the current value is fine.
     */
    private function assertThresholdOpen(AwardCycle $locked, CycleAttributes $changes): void
    {
        $score = $changes->disqualificationScore;

        if ($score instanceof Percent && ! $score->equals($locked->disqualification_score) && $this->thresholdLock->isLocked($locked->id)) {
            throw AwardRejected::thresholdLocked();
        }
    }

    private function assertSchedule(AwardCycle $locked, CycleAttributes $changes): void
    {
        if ($changes->dates === []) {
            return;
        }

        $schedule = CycleSchedule::of($locked)->with($changes->dates);
        $endsInPast = $locked->status === AwardCycleStatus::SubmissionOpen && $schedule->submissionEnd->lessThanOrEqualTo(Date::now());

        if (! $schedule->isOrdered() || $endsInPast) {
            throw AwardRejected::datesUnordered();
        }
    }

    /**
     * @param  list<int>|null  $regionIds
     */
    private function assertRegionsKeepRequests(AwardCycle $locked, ?array $regionIds): void
    {
        if ($regionIds === null) {
            return;
        }

        $removed = array_values(array_diff($locked->regions->modelKeys(), $regionIds));

        if ($removed !== [] && Assessment::query()->inCycle($locked->id)->whereIn('region_id', $removed)->exists()) {
            throw AwardRejected::regionHasRequests();
        }
    }
}
