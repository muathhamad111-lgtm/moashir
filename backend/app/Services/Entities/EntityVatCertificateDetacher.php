<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\StoredFileRemover;
use Illuminate\Support\Facades\DB;

final readonly class EntityVatCertificateDetacher
{
    public function __construct(private StoredFileRemover $remover, private AuditRecorder $audit) {}

    public function detach(Entity $entity): void
    {
        $certificate = $entity->vatCertificate()->withTrashed()->first();

        if (! $certificate instanceof StoredFile) {
            return;
        }

        $entity->forceFill(['vat_certificate_file_id' => null]);

        $this->audit->record(new AuditEntry(AuditEvent::EntityVatCertificateRemoved, $entity, ['vat_certificate_file_id' => $certificate->public_id], ['vat_certificate_file_id' => null]));

        $this->release($certificate);
    }

    public function detachUnlessRegistered(Entity $entity): void
    {
        if (! $entity->isVatRegistered() && $entity->vat_certificate_file_id !== null) {
            $this->detach($entity);
        }
    }

    public function release(StoredFile $certificate): void
    {
        DB::afterCommit(fn () => $this->remover->remove($certificate));
    }
}
