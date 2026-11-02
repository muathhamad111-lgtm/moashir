<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\FileIntake;
use App\Services\Files\StoredFileDisk;
use App\Services\Files\StoredFileRemover;
use Illuminate\Http\UploadedFile;

final readonly class ApplePayAssociationFile
{
    private const StoredFilePurpose PURPOSE = StoredFilePurpose::ApplePayDomainAssociation;

    public function __construct(
        private FileIntake $intake,
        private StoredFileRemover $remover,
        private StoredFileDisk $disk,
        private AuditRecorder $audit,
    ) {}

    public function replace(UploadedFile $upload, User $actor): StoredFile
    {
        $file = $this->intake->accept($upload, self::PURPOSE, $actor, 'file');

        StoredFile::query()->ofPurpose(self::PURPOSE)->whereKeyNot($file->id)->get()->each(fn (StoredFile $previous) => $this->remover->remove($previous));

        $this->audit->record(new AuditEntry(AuditEvent::ApplePayFileUploaded, $file, [], ['sha256' => $file->sha256, 'size_bytes' => $file->size_bytes]));

        return $file->setRelation('uploadedBy', $actor);
    }

    public function remove(): bool
    {
        $current = $this->current();

        if (! $current instanceof StoredFile) {
            return false;
        }

        $this->remover->remove($current);
        $this->audit->record(new AuditEntry(AuditEvent::ApplePayFileRemoved, $current, ['sha256' => $current->sha256], []));

        return true;
    }

    public function current(): ?StoredFile
    {
        return StoredFile::query()->with('uploadedBy')->ofPurpose(self::PURPOSE)->clean()->latest('id')->first();
    }

    public function contents(): ?string
    {
        $current = $this->current();

        return $current instanceof StoredFile ? $this->disk->contents($current) : null;
    }
}
