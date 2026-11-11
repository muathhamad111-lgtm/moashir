<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\EntityContactData;
use App\Data\Entities\EntityProfileData;
use App\Enums\Audit\AuditEvent;
use App\Events\Entities\EntityProfileUpdated;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityFreezeGuard;
use App\Services\Entities\EntityProfileWriter;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;

final readonly class UpdateEntityOnBehalf
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private EntityProfileWriter $writer,
        private RequestContext $context,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, Entity $entity, EntityProfileData $profile, EntityContactData $contact, string $reason): Entity
    {
        $this->freezeGuard->assertNotFrozen($entity);
        $this->context->recordReason($reason);

        DB::transaction(function () use ($actor, $entity, $profile, $contact): void {
            $changes = $this->writer->writeProfile($entity, $profile, $contact, $actor);
            $audited = $changes->withoutPersonalData();

            $this->audit->record(new AuditEntry(AuditEvent::EntityUpdatedByStaff, $entity, $audited->before, $audited->after));

            if ($changes->touchesEntityRecord()) {
                EntityProfileUpdated::dispatch($entity, $changes->fields(), $actor->id);
            }
        });

        return $entity->refresh();
    }
}
