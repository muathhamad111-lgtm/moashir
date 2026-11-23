<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\EntityProfileWrite;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\ProfileSection;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;

/**
 * Applies the entity's marketing consent at once: it is the entity's own PDPL consent, which it may withdraw at any
 * time, so it never waits for staff approval (operator decision, 2026-11-22).
 */
final readonly class OwnMarketingConsentWriter
{
    public function __construct(private EntityProfileWriter $writer, private AuditRecorder $audit) {}

    public function write(Entity $entity, ?bool $consents, User $owner): void
    {
        if ($consents === null || $consents === ($entity->marketing_consent_at !== null)) {
            return;
        }

        $changes = $this->writer->write($entity, new EntityProfileWrite([], null, $consents), $owner);

        if ($changes->isEmpty()) {
            return;
        }

        $this->audit->record(new AuditEntry(AuditEvent::EntityProfileUpdated, $entity, $changes->before, [
            ...$changes->after,
            'section' => ProfileSection::Contact->value,
            'changed_fields' => array_keys($changes->after),
        ]));
    }
}
