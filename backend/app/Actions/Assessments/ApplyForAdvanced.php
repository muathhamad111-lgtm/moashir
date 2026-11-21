<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Assessments\AdvancedApplication;
use App\Data\Award\AdvancedEligibility;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Finance\PaymentPurpose;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Exceptions\Award\AwardRejected;
use App\Exceptions\Finance\PaymentRejected;
use App\Exceptions\Paths\PathRejected;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Models\Identity\User;
use App\Services\Assessments\AdvancedRequestOpener;
use App\Services\Assessments\AssessmentEntityGuard;
use App\Services\Award\AdvancedEligibilityChecker;
use App\Services\Award\AdvancedRejectionRecorder;
use App\Services\Award\AwardEntryLedger;
use App\Services\Award\ConditionFailureMessages;
use App\Services\Paths\PathAvailabilityGuard;
use App\Services\Paths\PathPricingReader;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ApplyForAdvanced
{
    public function __construct(
        private AssessmentEntityGuard $entityGuard,
        private AdvancedEligibilityChecker $eligibility,
        private AdvancedRequestOpener $opener,
        private AdvancedRejectionRecorder $rejections,
        private AwardEntryLedger $ledger,
        private ConditionFailureMessages $messages,
        private PathPricingReader $pricing,
        private PathAvailabilityGuard $availability,
    ) {}

    public function handle(User $actor, int $cycleId): AdvancedApplication
    {
        $entity = Entity::withTrashed()->find($actor->entity_id) ?? throw AssessmentRejected::entityRequired();
        $this->entityGuard->assertMayAct($entity);
        $this->availability->assertAvailable(PaymentPurpose::Award, $this->pricing->award(AwardCycle::query()->find($cycleId)));

        try {
            $application = DB::transaction(fn (): AdvancedApplication => $this->apply($actor, $entity, $cycleId));
        } catch (UniqueConstraintViolationException) {
            throw AwardRejected::requestExists($this->ledger->requestIn($entity->id, $cycleId)->id ?? 0);
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

        $existing = $this->ledger->requestIn($entity->id, $cycle->id);

        if ($existing instanceof Assessment) {
            return $existing->status === AdvancedAssessmentStatus::Draft ? new AdvancedApplication($existing, false) : throw AwardRejected::requestExists($existing->id);
        }

        $openOrder = $this->ledger->openOrder($entity->id, $cycle->id);

        if ($openOrder instanceof Payment) {
            throw PaymentRejected::openOrderExists($openOrder);
        }

        $eligibility = $this->eligibility->check($entity, $cycle, Date::now());

        if (! $eligibility->meetsConditions()) {
            return new AdvancedApplication($this->rejections->record($actor, $entity, $cycle, $eligibility), false, $eligibility);
        }

        $this->assertFree($entity, $cycle);

        return new AdvancedApplication($this->opener->openDraft($entity, $cycle, $actor->id), true);
    }

    private function assertFree(Entity $entity, AwardCycle $cycle): void
    {
        $pricing = $this->pricing->award($cycle);

        if (! $pricing->requiresPayment()) {
            return;
        }

        if ($this->ledger->hasPaidOrderAwaitingDraft($entity->id, $cycle->id)) {
            throw AwardRejected::paymentBeingProcessed();
        }

        throw PathRejected::paymentRequired(PaymentPurpose::Award, $pricing, $cycle->id);
    }
}
