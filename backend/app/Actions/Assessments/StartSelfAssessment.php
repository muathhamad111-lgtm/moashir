<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Audit\AuditEvent;
use App\Events\Assessments\SelfAssessmentStarted;
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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final readonly class StartSelfAssessment
{
    public function __construct(
        private AssessmentEntityGuard $entityGuard,
        private RequestNumberAllocator $numbers,
        private AssessmentStateMachine $stateMachine,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor): Assessment
    {
        $entity = Entity::withTrashed()->find($actor->entity_id) ?? throw AssessmentRejected::entityRequired();
        $this->entityGuard->assertMayAct($entity);
        $version = CriteriaVersion::query()->published()->first() ?? throw CriteriaVersionRejected::noPublishedVersion();

        try {
            return DB::transaction(fn (): Assessment => $this->open($actor, $entity, $version));
        } catch (UniqueConstraintViolationException) {
            throw AssessmentRejected::selfDraftExists($this->openDraftId($entity) ?? 0);
        }
    }

    private function open(User $actor, Entity $entity, CriteriaVersion $version): Assessment
    {
        Entity::query()->whereKey($entity->id)->lockForUpdate()->first();
        $existing = $this->openDraftId($entity);

        if ($existing !== null) {
            throw AssessmentRejected::selfDraftExists($existing);
        }

        $path = AssessmentPath::SelfAssessment;
        $assessment = Assessment::query()->create([
            'request_number' => $this->numbers->next($path),
            'entity_id' => $entity->id,
            'path' => $path,
            'status' => $path->initialState(),
            'criteria_version_id' => $version->id,
            'scale_kind' => $version->scale_kind ?? throw CriteriaVersionRejected::noPublishedVersion(),
        ]);
        $assessment->setRelation('entity', $entity)->setRelation('currentResult', null);

        $this->stateMachine->recordStart($assessment, $actor->id);
        $this->audit->record(new AuditEntry(AuditEvent::AssessmentCreated, $assessment, [], [
            'request_number' => $assessment->request_number,
            'path' => $path->value,
            'status' => (string) $assessment->status->value,
            'criteria_version_id' => $version->id,
            'scale_kind' => $assessment->scale_kind->value,
        ]));

        SelfAssessmentStarted::dispatch($assessment);

        return $assessment;
    }

    private function openDraftId(Entity $entity): ?int
    {
        $id = Assessment::query()->ownedBy($entity->id)->ofPath(AssessmentPath::SelfAssessment)->open()->value('id');

        return is_int($id) ? $id : null;
    }
}
