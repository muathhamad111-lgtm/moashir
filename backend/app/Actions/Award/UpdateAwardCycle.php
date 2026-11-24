<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\CycleAttributes;
use App\Enums\Audit\AuditEvent;
use App\Events\Award\ParticipationCertificatesEnabled;
use App\Models\Award\AwardCycle;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\CycleAuditValues;
use App\Services\Award\CycleChangeGuard;
use App\Services\Award\CycleCriteriaVersionFollower;
use Illuminate\Support\Facades\DB;

final readonly class UpdateAwardCycle
{
    private const string CRITERIA_VERSION_FIELD = 'criteria_version_id';

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $rowLock,
        private CycleChangeGuard $guard,
        private CycleAuditValues $auditValues,
        private AuditRecorder $audit,
        private CycleCriteriaVersionFollower $criteria,
    ) {}

    public function handle(User $actor, AwardCycle $cycle, CycleAttributes $changes, ?string $reason): AwardCycle
    {
        if ($changes->isEmpty()) {
            return $cycle->load('regions');
        }

        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle, $changes): AwardCycle {
            $locked = $this->rowLock->lock($cycle->id);
            $this->guard->assertAllowed($actor, $locked, $changes);
            $version = $this->criteria->pendingFor($locked);
            $fields = $version instanceof CriteriaVersion ? [...$changes->touchedFields(), self::CRITERIA_VERSION_FIELD] : $changes->touchedFields();
            $before = $this->auditValues->of($locked, $fields);
            $gaveParticipation = $locked->participation_certificate_enabled;

            $this->apply($locked, $changes, $version);
            $this->audit->record(new AuditEntry(AuditEvent::CycleUpdated, $locked, $before, $this->auditValues->of($locked, $fields)));

            if (! $gaveParticipation && $locked->participation_certificate_enabled) {
                ParticipationCertificatesEnabled::dispatch($locked);
            }

            return $locked;
        });
    }

    private function apply(AwardCycle $locked, CycleAttributes $changes, ?CriteriaVersion $version): void
    {
        $locked->fill($changes->columnValues());

        if ($version instanceof CriteriaVersion) {
            $locked->fill([self::CRITERIA_VERSION_FIELD => $version->id, 'scale_kind' => null]);
        }

        $locked->save();

        if ($changes->regionIds !== null) {
            $locked->regions()->sync($changes->regionIds);
            $locked->load('regions');
        }
    }
}
