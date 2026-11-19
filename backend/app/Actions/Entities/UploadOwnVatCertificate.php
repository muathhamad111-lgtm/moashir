<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Files\StoredFilePurpose;
use App\Exceptions\Entities\VatCertificateRejected;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Entities\EntityVatCertificateDetacher;
use App\Services\Entities\OwnEntity;
use App\Services\Files\FileIntake;
use App\Services\Files\StoredFileRemover;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

final readonly class UploadOwnVatCertificate
{
    public const string FIELD = 'file';

    public function __construct(
        private OwnEntity $ownEntity,
        private FileIntake $intake,
        private EntityVatCertificateDetacher $certificates,
        private StoredFileRemover $remover,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $owner, UploadedFile $upload): Entity
    {
        $entity = $this->ownEntity->of($owner);
        $this->assertRegistered($entity);
        $certificate = $this->intake->accept($upload, StoredFilePurpose::VatCertificate, $owner, self::FIELD);

        try {
            DB::transaction(fn () => $this->attach($entity, $certificate));
        } catch (Throwable $failure) {
            $this->remover->remove($certificate);

            throw $failure;
        }

        return $entity->refresh();
    }

    private function assertRegistered(Entity $entity): void
    {
        if (! $entity->isVatRegistered()) {
            throw VatCertificateRejected::notRegistered();
        }
    }

    private function attach(Entity $entity, StoredFile $certificate): void
    {
        $locked = Entity::query()->whereKey($entity->id)->lockForUpdate()->firstOrFail();
        $this->assertRegistered($locked);
        $previous = $locked->vatCertificate()->withTrashed()->first();

        $certificate->attachable()->associate($locked)->save();
        $locked->forceFill(['vat_certificate_file_id' => $certificate->id])->save();

        $this->audit->record(new AuditEntry(AuditEvent::EntityVatCertificateUploaded, $locked, ['vat_certificate_file_id' => $previous?->public_id], [
            'vat_certificate_file_id' => $certificate->public_id,
            'mime_type' => $certificate->mime_type,
            'size_bytes' => $certificate->size_bytes,
            'sha256' => $certificate->sha256,
        ]));

        if ($previous instanceof StoredFile) {
            $this->certificates->release($previous);
        }
    }
}
