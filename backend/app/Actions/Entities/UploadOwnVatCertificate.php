<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Enums\Entities\ProfileChangeFile;
use App\Enums\Files\StoredFilePurpose;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\OwnEntity;
use App\Services\Entities\ProfileChanges\OwnProfileFileChanges;
use App\Services\Files\FileIntake;
use App\Services\Files\StoredFileRemover;
use Illuminate\Http\UploadedFile;
use Throwable;

final readonly class UploadOwnVatCertificate
{
    public const string FIELD = 'file';

    public function __construct(
        private OwnEntity $ownEntity,
        private FileIntake $intake,
        private OwnProfileFileChanges $changes,
        private StoredFileRemover $remover,
    ) {}

    public function handle(User $owner, UploadedFile $upload): Entity
    {
        $entity = $this->ownEntity->of($owner)->load('pendingProfileChange');
        $this->changes->assertMayHoldVatCertificate($entity, $entity->pendingProfileChange);
        $certificate = $this->intake->accept($upload, StoredFilePurpose::VatCertificate, $owner, self::FIELD);

        try {
            return $this->changes->proposeUpload($owner, ProfileChangeFile::VatCertificate, $certificate);
        } catch (Throwable $failure) {
            $this->remover->remove($certificate);

            throw $failure;
        }
    }
}
