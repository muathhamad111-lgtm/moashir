<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AdvancedApplication;
use App\Data\Award\AdvancedEligibility;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Events\Assessments\AdvancedConditionsRejected;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Exceptions\Award\AwardRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Assessments\AdvancedRequestOpener;
use App\Services\Assessments\AssessmentEntityGuard;
use App\Services\Award\AdvancedEligibilityChecker;
use App\Services\Award\ConditionFailureMessages;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ApplyForAdvanced
{
    public function __construct(
        private AssessmentEntityGuard $entityGuard,
        private AdvancedEligibilityChecker $eligibility,
        private AdvancedRequestOpener $opener,
        private ConditionFailureMessages $messages,
    ) {}

    public function handle(User $actor, int $cycleId): AdvancedApplication
    {
        $entity = Entity::withTrashed()->find($actor->entity_id) ?? throw AssessmentRejected::entityRequired();
        $this->entityGuard->assertMayAct($entity);

        try {
            $application = DB::transaction(fn (): AdvancedApplication => $this->apply($actor, $entity, $cycleId));
        } catch (UniqueConstraintViolationException) {
            throw AwardRejected::requestExists($this->existingRequestId($entity->id, $cycleId) ?? 0);
        }

        if ($application->rejection instanceof AdvancedEligibility) {
            throw AwardRejected::conditionsFailed($this->messages->fieldErrors($application->rejection), $application->rejection->failuresToArray());
        }

        return $application;
    }

    private function apply(User $actor, Entity $entity, int $cycleId): AdvancedApplication
    {
        Entity::query()->whereKey($entity->id)->lockForUpdate()->first();
        $cycle = AwardCycle::query()->with('regions')->whereKey($cycleId)->sharedLock()->first();

        if (! $cycle instanceof AwardCycle || ! $cycle->acceptsApplicationsAt(Date::now())) {
            throw AwardRejected::submissionClosed();
        }

        $existing = $this->existingRequest($entity->id, $cycle->id);

        if ($existing instanceof Assessment) {
            return $existing->status === AdvancedAssessmentStatus::Draft ? new AdvancedApplication($existing, false) : throw AwardRejected::requestExists($existing->id);
        }

        $eligibility = $this->eligibility->check($entity, $cycle, Date::now());

        if (! $eligibility->meetsConditions()) {
            return new AdvancedApplication($this->recordRejection($actor, $entity, $cycle, $eligibility), false, $eligibility);
        }

        return new AdvancedApplication($this->opener->openDraft($entity, $cycle, $actor->id), true);
    }

    private function recordRejection(User $actor, Entity $entity, AwardCycle $cycle, AdvancedEligibility $eligibility): Assessment
    {
        $recorded = Assessment::query()->ownedBy($entity->id)->inCycle($cycle->id)->where('status', AdvancedAssessmentStatus::RejectedConditions)->first();

        if ($recorded instanceof Assessment) {
            return $recorded;
        }

        $rejected = $this->opener->recordRejection($entity, $cycle, $eligibility, $actor->id);
        AdvancedConditionsRejected::dispatch($rejected, $actor->id);

        return $rejected;
    }

    private function existingRequest(int $entityId, int $cycleId): ?Assessment
    {
        return Assessment::query()->ownedBy($entityId)->inCycle($cycleId)->whereNot('status', AdvancedAssessmentStatus::RejectedConditions)->first();
    }

    private function existingRequestId(int $entityId, int $cycleId): ?int
    {
        return $this->existingRequest($entityId, $cycleId)?->id;
    }
}
