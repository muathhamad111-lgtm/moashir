<?php

declare(strict_types=1);

namespace App\Services\Entities\ProfileChanges;

use App\Data\Audit\AuditEntry;
use App\Data\Entities\ProfileChangeFileSet;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\ProfileChangeFile;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityLogoReleaser;
use App\Services\Entities\EntityVatCertificateDetacher;

/**
 * Sets an approved pending logo or VAT certificate on the entity, or removes the current one, and releases the file
 * it replaces. The caller saves the entity.
 */
final readonly class ProfileChangeFileSwap
{
    public function __construct(
        private EntityLogoReleaser $logos,
        private EntityVatCertificateDetacher $certificates,
        private AuditRecorder $audit,
    ) {}

    public function apply(Entity $entity, ProfileChangeFileSet $files, ProfileChangeFile $file): void
    {
        $pendingId = $files->pendingId($file);
        $next = $pendingId === null ? null : StoredFile::query()->findOrFail($pendingId);

        match ($file) {
            ProfileChangeFile::Logo => $this->swapLogo($entity, $next),
            ProfileChangeFile::VatCertificate => $this->swapVatCertificate($entity, $next),
        };
    }

    private function swapLogo(Entity $entity, ?StoredFile $next): void
    {
        $previous = $entity->logo()->withTrashed()->first();
        $entity->forceFill(['logo_file_id' => $next?->id]);

        $this->audit->record($next instanceof StoredFile
            ? new AuditEntry(AuditEvent::EntityLogoUploaded, $entity, ['logo_file_id' => $previous?->public_id], ['logo_file_id' => $next->public_id, ...$this->metadataOf($next)])
            : new AuditEntry(AuditEvent::EntityLogoRemoved, $entity, ['logo_file_id' => $previous?->public_id], ['logo_file_id' => null]));

        if ($previous instanceof StoredFile) {
            $this->logos->release($previous);
        }
    }

    private function swapVatCertificate(Entity $entity, ?StoredFile $next): void
    {
        if (! $next instanceof StoredFile) {
            $this->certificates->detach($entity);

            return;
        }

        $previous = $entity->vatCertificate()->withTrashed()->first();
        $entity->forceFill(['vat_certificate_file_id' => $next->id]);

        $this->audit->record(new AuditEntry(AuditEvent::EntityVatCertificateUploaded, $entity, ['vat_certificate_file_id' => $previous?->public_id], [
            'vat_certificate_file_id' => $next->public_id,
            ...$this->metadataOf($next),
        ]));

        if ($previous instanceof StoredFile) {
            $this->certificates->release($previous);
        }
    }

    /**
     * @return array{mime_type: string, size_bytes: int, sha256: string}
     */
    private function metadataOf(StoredFile $file): array
    {
        return ['mime_type' => $file->mime_type, 'size_bytes' => $file->size_bytes, 'sha256' => $file->sha256];
    }
}
