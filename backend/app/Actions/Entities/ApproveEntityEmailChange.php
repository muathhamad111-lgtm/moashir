<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Events\Entities\EntityEmailChangeApplied;
use App\Exceptions\IllegalStateTransition;
use App\Models\Entities\Entity;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityEmailChangeApplier;
use App\Services\Entities\EntityFreezeGuard;
use Illuminate\Support\Facades\DB;

final readonly class ApproveEntityEmailChange
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private EntityEmailChangeApplier $applier,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, EmailChangeRequest $request): EmailChangeRequest
    {
        if ($request->entity instanceof Entity) {
            $this->freezeGuard->assertNotFrozen($request->entity);
        }

        DB::transaction(function () use ($actor, $request): void {
            $locked = EmailChangeRequest::query()->whereKey($request->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isAwaitingApproval()) {
                throw IllegalStateTransition::from($locked->status->value, 'approve');
            }

            $this->applier->apply($request, $actor);

            if ($request->entity instanceof Entity) {
                $this->audit->record(new AuditEntry(AuditEvent::EntityEmailChangeApproved, $request->entity));
            }

            EntityEmailChangeApplied::dispatch($request, $actor->id);
        });

        return $request->refresh();
    }
}
