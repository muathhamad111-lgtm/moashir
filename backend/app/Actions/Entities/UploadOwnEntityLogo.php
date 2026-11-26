<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Enums\Entities\ProfileChangeFile;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\EntityLogoIntake;
use App\Services\Entities\OwnEntity;
use App\Services\Entities\ProfileChanges\OwnProfileFileChanges;
use App\Services\Files\StoredFileRemover;
use Illuminate\Http\UploadedFile;
use Throwable;

final readonly class UploadOwnEntityLogo
{
    public function __construct(
        private OwnEntity $ownEntity,
        private EntityLogoIntake $intake,
        private OwnProfileFileChanges $changes,
        private StoredFileRemover $remover,
    ) {}

    public function handle(User $owner, UploadedFile $upload): Entity
    {
        $logo = $this->intake->accept($upload, $this->ownEntity->of($owner), $owner);

        try {
            return $this->changes->proposeUpload($owner, ProfileChangeFile::Logo, $logo);
        } catch (Throwable $failure) {
            $this->remover->remove($logo);

            throw $failure;
        }
    }
}
