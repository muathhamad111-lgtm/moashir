<?php

declare(strict_types=1);

namespace App\Actions\Assessments;

use App\Data\Audit\AuditEntry;
use App\Enums\Assessments\RebindTrigger;
use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\RoleKey;
use App\Enums\Notifications\NotificationCode;
use App\Jobs\Assessments\SettleRestoredCriteriaUpdate;
use App\Models\Entities\Entity;
use App\Services\Assessments\CriteriaUpdateHold;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityNotifier;
use App\Support\Assessments\AssessmentNotices;
use App\Support\Entities\EntityLinks;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class SweepStaleCriteriaUpdateHolds
{
    public function __construct(
        private CriteriaUpdateHold $hold,
        private AuditRecorder $audit,
        private EntityNotifier $notifier,
        private Repository $config,
    ) {}

    public function handle(): int
    {
        $heldBefore = Date::now()->subMinutes($this->config->integer('moashir.assessment.criteria_update_hold_stale_minutes'));
        $redispatched = 0;

        foreach ($this->hold->staleEntityIds($heldBefore) as $entityId) {
            $redispatched += $this->redispatch($entityId) ? 1 : 0;
        }

        return $redispatched;
    }

    private function redispatch(int $entityId): bool
    {
        $entity = Entity::query()->find($entityId);

        if (! $entity instanceof Entity) {
            return false;
        }

        $sweep = DB::transaction(function () use ($entity): int {
            $held = $this->hold->lockHeldOf($entity->id);

            if ($held->isEmpty()) {
                return 0;
            }

            $sweep = $this->hold->countRedispatch($held);
            $this->audit->record(new AuditEntry(AuditEvent::AssessmentCriteriaUpdateRedispatched, $entity, [], [
                'trigger' => RebindTrigger::EntityRestore->value,
                'sweep' => $sweep,
                'assessment_ids' => $held->modelKeys(),
            ]));

            return $sweep;
        });

        if ($sweep === 0) {
            return false;
        }

        SettleRestoredCriteriaUpdate::dispatch($entity->id);

        if ($sweep === $this->config->integer('moashir.assessment.criteria_update_redispatch_alert_after')) {
            $this->alert($entity, $sweep);
        }

        return true;
    }

    private function alert(Entity $entity, int $sweep): void
    {
        $this->notifier->toRole(RoleKey::SystemAdmin, AssessmentNotices::fromKey(NotificationCode::RequestProcessingFailed, 'notifications.assessments.criteria_update_stalled', [
            'entity' => $entity->name->toLocalizedText(),
            'reference' => $entity->reference(),
            'automatic_retries' => LocalizedText::fromChoice('quantities.automatic_retries', $sweep),
        ], EntityLinks::record($entity->id))->inAppOnly(), null);
    }
}
