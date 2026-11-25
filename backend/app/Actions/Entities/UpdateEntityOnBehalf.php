<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\StaffEntityUpdate;
use App\Enums\Audit\AuditEvent;
use App\Events\Entities\EntityProfileUpdated;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityFreezeGuard;
use App\Services\Entities\EntityProfileWriter;
use App\Services\Entities\StaffAuthorizationLetter;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class UpdateEntityOnBehalf
{
    public function __construct(
        private EntityFreezeGuard $freezeGuard,
        private EntityProfileWriter $writer,
        private RequestContext $context,
        private AuditRecorder $audit,
        private StaffAuthorizationLetter $letters,
    ) {}

    public function handle(User $actor, Entity $entity, StaffEntityUpdate $update): Entity
    {
        $this->freezeGuard->assertNotFrozen($entity);
        $this->context->recordReason($update->reason);
        $letter = $this->letters->accept($entity, $update, $actor);

        try {
            DB::transaction(fn () => $this->write($actor, $entity, $update, $letter));
        } catch (Throwable $failure) {
            $this->letters->discardUnattached($entity, $letter);

            throw $failure;
        }

        return $entity->refresh();
    }

    private function write(User $actor, Entity $entity, StaffEntityUpdate $update, ?StoredFile $letter): void
    {
        $changes = $this->writer->writeProfile($entity, $update->profile, $update->contact, $actor);
        $this->letters->attach($entity, $letter);
        $audited = $changes->withoutPersonalData();

        $this->audit->record(new AuditEntry(AuditEvent::EntityUpdatedByStaff, $entity, $audited->before, $audited->after));

        if ($changes->touchesEntityRecord()) {
            EntityProfileUpdated::dispatch($entity, $changes->fields(), $actor->id);
        }
    }
}
