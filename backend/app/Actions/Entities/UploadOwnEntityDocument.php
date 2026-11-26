<?php

declare(strict_types=1);

namespace App\Actions\Entities;

use App\Enums\Entities\EntityDocument;
use App\Models\Entities\Entity;
use App\Models\Identity\User;
use App\Services\Entities\ProfileChanges\OwnProfileFileChanges;
use App\Services\Files\FileIntake;
use App\Services\Files\StoredFileRemover;
use Illuminate\Http\UploadedFile;
use Throwable;

/**
 * Proposes a new license certificate or authorization letter; it replaces the approved one once staff approve it.
 */
final readonly class UploadOwnEntityDocument
{
    public const string FIELD = 'file';

    public function __construct(
        private FileIntake $intake,
        private OwnProfileFileChanges $changes,
        private StoredFileRemover $remover,
    ) {}

    public function handle(User $owner, EntityDocument $document, UploadedFile $upload): Entity
    {
        $file = $this->intake->accept($upload, $document->purpose(), $owner, self::FIELD);

        try {
            return $this->changes->proposeUpload($owner, $document->profileChangeFile(), $file);
        } catch (Throwable $failure) {
            $this->remover->remove($file);

            throw $failure;
        }
    }
}
