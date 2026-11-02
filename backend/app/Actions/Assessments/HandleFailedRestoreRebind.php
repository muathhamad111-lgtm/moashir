<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\RebindTrigger;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Models\Entities\Entity;
use App\Services\Assessments\CriteriaUpdateHold;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentNotices;
use App\Support\Entities\EntityLinks;
use Illuminate\Support\Facades\DB;

final readonly class HandleFailedRestoreRebind
{
    public function __construct(
        private CriteriaUpdateHold $hold,
        private AuditRecorder $audit,
        private EntityNotifier $notifier,
    ) {}

    public function handle(Entity $entity): int
    {
        $released = $this->releaseHeldDrafts($entity->id);

        $this->notifier->toRole(RoleKey::SystemAdmin, AssessmentNotices::for(NotificationCode::RequestProcessingFailed, [
            'entity' => $entity->name->toLocalizedText(),
            'reference' => $entity->reference(),
            'count' => (string) $released,
        ], EntityLinks::record($entity->id))->inAppOnly(), null);

        return $released;
    }

    private function releaseHeldDrafts(int $entityId): int
    {
        return DB::transaction(function () use ($entityId): int {
            $held = $this->hold->lockHeldOf($entityId);

            foreach ($held as $draft) {
                $this->hold->release($draft->id);
                $this->audit->record(new AuditEntry(
                    AuditEvent::AssessmentCriteriaUpdateAbandoned,
                    $draft,
                    ['criteria_update_pending' => true],
                    [
                        'criteria_update_pending' => false,
                        'criteria_version_id' => $draft->criteria_version_id,
                        'trigger' => RebindTrigger::EntityRestore->value,
                    ],
                ));
            }

            return $held->count();
        });
    }
}
