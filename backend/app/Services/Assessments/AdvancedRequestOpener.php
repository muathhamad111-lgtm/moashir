<?php

declare(strict_types=1);

namespace App\Services\Assessments;

use App\Data\Audit\AuditEntry;
use App\Data\Award\AdvancedEligibility;
use App\Enums\Assessments\AdvancedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\AssessmentVerb;
use App\Enums\Audit\AuditEvent;
use App\Models\Assessments\Assessment;
use App\Models\Award\AwardCycle;
use App\Models\Entities\Entity;
use App\Models\Finance\Payment;
use App\Services\Audit\AuditRecorder;

final readonly class AdvancedRequestOpener
{
    public function __construct(
        private RequestNumberAllocator $numbers,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function openDraft(Entity $entity, AwardCycle $cycle, int $actorId): Assessment
    {
        $assessment = $this->create($entity, $cycle, AdvancedAssessmentStatus::Draft, null);
        $this->stateMachine->recordStart($assessment, $actorId);
        $this->audit->record(new AuditEntry(AuditEvent::AwardApplicationCreated, $assessment, [], $this->auditValues($assessment)));

        return $assessment;
    }

    public function openPaidDraft(Entity $entity, AwardCycle $cycle, Payment $payment): Assessment
    {
        $assessment = $this->create($entity, $cycle, AdvancedAssessmentStatus::Draft, null, $payment->id);
        $this->stateMachine->recordStart($assessment, null);
        $this->audit->record(new AuditEntry(AuditEvent::AwardApplicationCreated, $assessment, [], [
            ...$this->auditValues($assessment),
            'payment_reference' => $payment->reference,
        ]));

        return $assessment->setRelation('payment', $payment);
    }

    public function recordRejection(Entity $entity, AwardCycle $cycle, AdvancedEligibility $eligibility, int $actorId): Assessment
    {
        $assessment = $this->create($entity, $cycle, AdvancedAssessmentStatus::RejectedConditions, ['failures' => $eligibility->failuresToArray()]);
        $this->stateMachine->recordStart($assessment, $actorId, AssessmentVerb::RejectConditions);
        $this->audit->record(new AuditEntry(AuditEvent::AwardApplicationRejected, $assessment, [], [
            ...$this->auditValues($assessment),
            'failed_conditions' => array_column($eligibility->failuresToArray(), 'rule'),
        ]));

        return $assessment;
    }

    /**
     * @param  array<string, mixed>|null  $conditionFailure
     */
    private function create(Entity $entity, AwardCycle $cycle, AdvancedAssessmentStatus $status, ?array $conditionFailure, ?int $paymentId = null): Assessment
    {
        $path = AssessmentPath::Advanced;
        $assessment = Assessment::query()->create([
            'request_number' => $this->numbers->next($path),
            'entity_id' => $entity->id,
            'path' => $path,
            'status' => $status,
            'criteria_version_id' => $cycle->criteria_version_id,
            'scale_kind' => $cycle->scale_kind,
            'award_cycle_id' => $cycle->id,
            'region_id' => $entity->region_id,
            'condition_failure' => $conditionFailure,
            'payment_id' => $paymentId,
        ]);

        return $assessment->setRelation('entity', $entity)->setRelation('awardCycle', $cycle)->setRelation('currentResult', null);
    }

    /**
     * @return array<string, mixed>
     */
    private function auditValues(Assessment $assessment): array
    {
        return [
            'request_number' => $assessment->request_number,
            'path' => AssessmentPath::Advanced->value,
            'status' => (string) $assessment->status->value,
            'award_cycle_id' => $assessment->award_cycle_id,
            'criteria_version_id' => $assessment->criteria_version_id,
            'scale_kind' => $assessment->scale_kind->value,
        ];
    }
}
