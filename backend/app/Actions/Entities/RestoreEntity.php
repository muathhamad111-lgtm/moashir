<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\OpenEntityRequest;
use App\Enums\Audit\AuditEvent;
use App\Events\Entities\EntityRestored;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityDeadlineFreezer;
use App\Services\Entities\EntityRestoreSteps;
use App\Services\Entities\OpenRequestDirectory;
use App\Support\RequestContext;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class RestoreEntity
{
    public function __construct(
        private OpenRequestDirectory $openRequests,
        private EntityDeadlineFreezer $deadlines,
        private EntityRestoreSteps $restoreSteps,
        private RequestContext $context,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Entity $entity, string $reason): Entity
    {
        if (! $entity->trashed()) {
            throw IllegalStateTransition::from($entity->status->value, 'restore');
        }

        $this->context->recordReason($reason);

        DB::transaction(function () use ($actor, $entity): void {
            $entity->restore();
            $entity->forceFill(['restored_at' => Date::now(), 'deleted_by_user_id' => null, 'deletion_reason' => null])->save();
            $resumedDeadlines = $this->deadlines->resume($entity);
            $this->restoreSteps->runFor($entity);

            $unfrozenNumbers = array_map(
                static fn (OpenEntityRequest $request): string => $request->number,
                $this->openRequests->openRequestsOf($entity),
            );

            $this->audit->record(new AuditEntry(
                AuditEvent::EntityRestored,
                $entity,
                ['deleted' => true],
                ['deleted' => false, 'unfrozen_request_numbers' => $unfrozenNumbers, 'resumed_deadlines' => $resumedDeadlines],
            ));

            EntityRestored::dispatch($entity, $unfrozenNumbers, $actor->id);
        });

        return $entity;
    }
}
