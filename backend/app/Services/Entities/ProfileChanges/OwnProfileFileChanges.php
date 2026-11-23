<?php

declare(strict_types=1);

namespace App\Services\Entities\ProfileChanges;

use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileChangeFile;
use App\Exceptions\Entities\VatCertificateRejected;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use Illuminate\Support\Facades\DB;

/**
 * Puts the entity's logo or VAT certificate upload or removal into its pending change request. Pending files stay
 * private and are never set on the entity until staff approve them.
 */
final readonly class OwnProfileFileChanges
{
    public function __construct(private PendingProfileChanges $pending, private ProfileChangeFileDiscarder $files) {}

    public function proposeUpload(User $owner, ProfileChangeFile $file, StoredFile $upload): Entity
    {
        return DB::transaction(function () use ($owner, $file, $upload): Entity {
            [$entity, $request] = $this->openFor($owner, $file);

            if ($file === ProfileChangeFile::VatCertificate) {
                $this->assertMayHoldVatCertificate($entity, $request);
            }

            $upload->attachable()->associate($entity)->save();
            $request->setFileChange($file, $upload->id, false);
            $this->pending->store($request, $owner);

            return $entity;
        });
    }

    public function proposeRemoval(User $owner, ProfileChangeFile $file): Entity
    {
        return DB::transaction(function () use ($owner, $file): Entity {
            [$entity, $request] = $this->openFor($owner, $file);

            if ($this->approvedFileId($entity, $file) === null) {
                $request->setFileChange($file, null, false);
            } else {
                $request->setFileChange($file, null, true);
            }

            $this->pending->store($request, $owner);

            return $entity;
        });
    }

    public function assertMayHoldVatCertificate(Entity $entity, ?EntityProfileChangeRequest $request): void
    {
        $proposed = $request->proposed ?? [];
        $registered = array_key_exists(EntityProfileField::VatRegistered->value, $proposed)
            ? $proposed[EntityProfileField::VatRegistered->value] === true
            : $entity->isVatRegistered();

        if (! $registered) {
            throw VatCertificateRejected::notRegistered();
        }
    }

    /**
     * @return array{Entity, EntityProfileChangeRequest}
     */
    private function openFor(User $owner, ProfileChangeFile $file): array
    {
        $entity = Entity::query()->whereKey($owner->entity_id)->lockForUpdate()->firstOrFail();
        $request = $this->pending->openFor($entity, $owner);
        $this->files->discard([$request->fileChanges()->pendingId($file)]);

        return [$entity, $request];
    }

    private function approvedFileId(Entity $entity, ProfileChangeFile $file): ?int
    {
        return match ($file) {
            ProfileChangeFile::Logo => $entity->logo_file_id,
            ProfileChangeFile::VatCertificate => $entity->vat_certificate_file_id,
        };
    }
}
