<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\ErasureOutcome;
use App\Data\Entities\PermanentDeletionInstruction;
use App\Enums\Audit\AuditEvent;
use App\Enums\Notifications\NotificationCode;
use App\Events\Entities\EntityPermanentlyDeleted;
use App\Exceptions\Entities\EntityRejected;
use App\Models\Entities\Entity;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityEraser;
use App\Services\Notifications\SecurityAlerter;
use App\Support\RequestContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class PermanentlyDeleteEntity
{
    public function __construct(
        private EntityEraser $eraser,
        private RequestContext $context,
        private AuditRecorder $audit,
        private SecurityAlerter $alerter,
    ) {}

    public function handle(Entity $entity, PermanentDeletionInstruction $instruction): ErasureOutcome
    {
        $this->assertErasable($entity, $instruction);
        $this->context->recordReason($instruction->reason);
        $reference = $entity->reference();

        $outcome = DB::transaction(function () use ($entity, $reference): ErasureOutcome {
            $outcome = $this->eraser->erase($entity);

            $this->audit->record(new AuditEntry(
                AuditEvent::EntityPermanentlyDeleted,
                $entity,
                ['reference' => $reference],
                ['pseudonym' => $outcome->pseudonym, 'kept' => $outcome->toArray()['kept']],
                ['security'],
            ));

            EntityPermanentlyDeleted::dispatch($entity->id, $reference);

            return $outcome;
        });

        $this->alerter->raise(NotificationCode::ReasonRequiredAction);

        return $outcome;
    }

    private function assertErasable(Entity $entity, PermanentDeletionInstruction $instruction): void
    {
        if (! $entity->trashed()) {
            throw EntityRejected::notSoftDeleted();
        }

        if (! $instruction->disclaimerAcknowledged) {
            throw EntityRejected::disclaimerRequired();
        }

        if (! hash_equals($entity->reference(), Str::upper(trim($instruction->confirmation)))) {
            throw EntityRejected::confirmationMismatch();
        }
    }
}
