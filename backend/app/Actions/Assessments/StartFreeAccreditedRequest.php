<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Enums\Finance\PaymentPurpose;
use App\Events\Assessments\AccreditedRequestOpened;
use App\Exceptions\Assessments\AssessmentRejected;
use App\Exceptions\Criteria\CriteriaVersionRejected;
use App\Models\Assessments\Assessment;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Assessments\AssessmentEntityGuard;
use App\Services\Assessments\AssessmentStateMachine;
use App\Services\Assessments\RequestNumberAllocator;
use App\Services\Audit\AuditRecorder;
use App\Services\Paths\PathPricingReader;
use App\Services\Paths\PathStartGuard;
use App\Support\ScalarValue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class StartFreeAccreditedRequest
{
    public function __construct(
        private AssessmentEntityGuard $entityGuard,
        private PathPricingReader $pricing,
        private PathStartGuard $startGuard,
        private RequestNumberAllocator $numbers,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor): Assessment
    {
        $entity = Entity::withTrashed()->find($actor->entity_id) ?? throw AssessmentRejected::entityRequired();
        $this->entityGuard->assertMayAct($entity);

        try {
            return DB::transaction(fn (): Assessment => $this->open($actor, $entity))->load(ListAssessments::RELATIONS);
        } catch (UniqueConstraintViolationException) {
            throw AssessmentRejected::accreditedRequestOpen($this->openRequestId($entity));
        }
    }

    private function open(User $actor, Entity $entity): Assessment
    {
        Entity::query()->whereKey($entity->id)->lockForUpdate()->first();
        $this->startGuard->assertMayStartFree($entity, PaymentPurpose::Accredited, $this->pricing->accredited());
        $version = CriteriaVersion::query()->published()->first();

        if (! $version instanceof CriteriaVersion || $version->scale_kind === null) {
            throw CriteriaVersionRejected::noPublishedVersion();
        }

        $path = AssessmentPath::Accredited;
        $assessment = Assessment::query()->create([
            'request_number' => $this->numbers->next($path),
            'entity_id' => $entity->id,
            'path' => $path,
            'status' => AccreditedAssessmentStatus::Draft,
            'criteria_version_id' => $version->id,
            'scale_kind' => $version->scale_kind,
        ]);
        $assessment->setRelation('entity', $entity);

        $this->stateMachine->recordStart($assessment, $actor->id);
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentCreated, $assessment, [], [
            'request_number' => $assessment->request_number,
            'path' => $path->value,
            'status' => AccreditedAssessmentStatus::Draft->value,
            'payment_reference' => null,
            'criteria_version_id' => $version->id,
            'scale_kind' => $version->scale_kind->value,
        ]));

        AccreditedRequestOpened::dispatch($assessment);

        return $assessment;
    }

    private function openRequestId(Entity $entity): int
    {
        return ScalarValue::int(Assessment::query()->ownedBy($entity->id)->ofPath(AssessmentPath::Accredited)->open()->value('id'));
    }
}
