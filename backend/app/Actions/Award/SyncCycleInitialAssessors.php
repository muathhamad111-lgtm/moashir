<?php

declare(strict_types=1);

namespace App\Actions\Award;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Award\CandidateRole;
use App\Exceptions\Award\AwardRejected;
use App\Exceptions\IllegalStateTransition;
use App\Models\Award\AwardCycle;
use App\Models\Award\AwardCycleAssessor;
use App\Models\Identity\User;
use App\Services\Assessments\StaffActionReason;
use App\Services\Audit\AuditRecorder;
use App\Services\Award\AwardCycleRowLock;
use App\Services\Award\CycleSegregation;
use App\Services\Award\CycleStaffEligibility;
use App\Support\ScalarValue;
use Illuminate\Support\Facades\DB;

final readonly class SyncCycleInitialAssessors
{
    private const string VERB = 'update_initial_assessors';

    public function __construct(
        private StaffActionReason $reason,
        private AwardCycleRowLock $rowLock,
        private CycleStaffEligibility $eligibility,
        private CycleSegregation $segregation,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param  list<int>  $assessorIds
     */
    public function handle(User $actor, AwardCycle $cycle, array $assessorIds, ?string $reason): AwardCycle
    {
        $this->reason->record($actor, $reason);

        return DB::transaction(function () use ($actor, $cycle, $assessorIds): AwardCycle {
            $locked = $this->rowLock->lock($cycle->id);

            if (! $locked->status->acceptsRosterChanges()) {
                throw IllegalStateTransition::from($locked->status->value, self::VERB);
            }

            $current = $this->activeIdsOf($locked);
            $added = array_values(array_diff($assessorIds, $current));
            $removed = array_values(array_diff($current, $assessorIds));

            foreach ($added as $assessorId) {
                $this->link($actor, $locked, $assessorId, 'assessor_ids.'.(int) array_search($assessorId, $assessorIds, true));
            }

            AwardCycleAssessor::query()->where('award_cycle_id', $locked->id)->whereIn('assessor_user_id', $removed)->update(['is_active' => false]);
            $this->audit->record(new AuditEntry(AuditEvent::CycleInitialAssessorsChanged, $locked, [], ['added' => $added, 'removed' => $removed]));

            return $locked;
        });
    }

    private function link(User $actor, AwardCycle $cycle, int $assessorId, string $field): void
    {
        $this->eligibility->find(CandidateRole::InitialAssessor, $assessorId) ?? throw AwardRejected::initialAssessorInvalid($field);
        $this->segregation->assertMayAssessInitially($cycle->id, $assessorId, $field);

        AwardCycleAssessor::query()->updateOrCreate(
            ['award_cycle_id' => $cycle->id, 'assessor_user_id' => $assessorId],
            ['is_active' => true, 'added_by_user_id' => $actor->id],
        );
    }

    /**
     * @return list<int>
     */
    private function activeIdsOf(AwardCycle $cycle): array
    {
        return array_values(AwardCycleAssessor::query()
            ->where('award_cycle_id', $cycle->id)
            ->active()
            ->pluck('assessor_user_id')
            ->map(static fn (mixed $id): int => ScalarValue::int($id))
            ->all());
    }
}
