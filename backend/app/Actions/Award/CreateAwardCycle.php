<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Data\Award\CycleAttributes;
use App\Data\Award\CycleSchedule;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\AwardCycleStatus;
use App\Enums\Award\CycleMemberRole;
use App\Enums\Identity\PermissionKey;
use App\Exceptions\Award\AwardRejected;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardCycleMember;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Region;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\CycleCriteriaVersionFollower;
use App\Services\Award\CycleDefaultsReader;
use App\Services\Award\CycleFieldLocks;
use App\Support\ScalarValue;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\DB;

final readonly class CreateAwardCycle
{
    public function __construct(
        private StaffActionReason $reason,
        private CycleFieldLocks $locks,
        private CycleDefaultsReader $defaults,
        private AuditRecorder $audit,
        private Repository $config,
        private CycleCriteriaVersionFollower $criteria,
    ) {}

    public function handle(User $actor, CycleAttributes $attributes, ?string $reason): AwardCycle
    {
        $this->reason->record($actor, $reason);
        $this->locks->assertEditable($actor, AwardCycleStatus::Draft, $attributes);
        $schedule = CycleSchedule::from($attributes->dates);

        if (! $schedule instanceof CycleSchedule || ! $schedule->isOrdered()) {
            throw AwardRejected::datesUnordered();
        }

        $version = $this->criteria->published();

        return DB::transaction(fn (): AwardCycle => $this->create($actor, $attributes, $schedule, $version));
    }

    private function create(User $actor, CycleAttributes $attributes, CycleSchedule $schedule, CriteriaVersion $version): AwardCycle
    {
        $defaults = $this->defaults->read();
        $cycle = AwardCycle::query()->create([
            ...$attributes->columnValues(),
            'year' => $attributes->year ?? $schedule->submissionStart->setTimezone($this->config->string('app.display_timezone'))->year,
            'status' => AwardCycleStatus::Draft,
            'criteria_version_id' => $version->id,
            'min_entity_age_years' => $attributes->minEntityAgeYears ?? $defaults->minEntityAgeYears,
            'disqualification_score' => $attributes->disqualificationScore ?? $defaults->disqualificationScore,
            'initial_review_sla_days' => $attributes->initialReviewSlaDays ?? $defaults->initialReviewSlaDays,
            'participation_certificate_enabled' => $attributes->participationCertificateEnabled ?? false,
            'entry_fee' => ($attributes->entryFee ?? $defaults->entryFee)->amount,
            'created_by_user_id' => $actor->id,
        ]);
        $regionIds = $attributes->regionIds ?? $this->activeRegionIds();
        $cycle->regions()->sync($regionIds);

        if (! $actor->isSuperAdmin() && $actor->can(PermissionKey::AwardSecretaryDecide->value)) {
            AwardCycleMember::query()->create(['award_cycle_id' => $cycle->id, 'user_id' => $actor->id, 'role' => CycleMemberRole::AwardSecretary, 'added_by_user_id' => $actor->id]);
        }

        $this->audit->record(new AuditEntry(AuditEvent::CycleCreated, $cycle, [], [
            'status' => AwardCycleStatus::Draft->value,
            'criteria_version_id' => $version->id,
            'region_ids' => $regionIds,
            'submission_start' => $schedule->submissionStart->toIso8601String(),
            'submission_end' => $schedule->submissionEnd->toIso8601String(),
            'entry_fee' => $cycle->entry_fee,
        ]));

        return $cycle->load('regions');
    }

    /**
     * @return list<int>
     */
    private function activeRegionIds(): array
    {
        return array_values(Region::query()->active()->orderBy('id')->pluck('id')->map(static fn (mixed $id): int => ScalarValue::int($id))->all());
    }
}
