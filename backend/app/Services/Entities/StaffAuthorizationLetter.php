<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\StaffEntityUpdate;
use App\Enums\Entities\EntityDocument;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Entities\ProfileChanges\AuthorizationLetterRequirement;
use App\Services\Files\FileIntake;
use App\Services\Files\StoredFileRemover;
use Illuminate\Http\UploadedFile;

/**
 * The authorization letter staff attach when they edit the entity on its behalf: required with a new authorized
 * person (operator decision, 2026-11-25), taken in before the edit, set on the entity within it, and removed again
 * when the edit fails.
 */
final readonly class StaffAuthorizationLetter
{
    public const string FIELD = 'authorization_letter';

    private const EntityDocument LETTER = EntityDocument::AuthorizationLetter;

    public function __construct(
        private AuthorizationLetterRequirement $requirement,
        private FileIntake $intake,
        private EntityDocumentAttachments $attachments,
        private StoredFileRemover $remover,
    ) {}

    public function accept(Entity $entity, StaffEntityUpdate $update, User $actor): ?StoredFile
    {
        $this->requirement->assertStaffEdit($entity->loadMissing('owner')->owner, $update->contact, $update->authorizationLetter);

        return $update->authorizationLetter instanceof UploadedFile
            ? $this->intake->accept($update->authorizationLetter, self::LETTER->purpose(), $actor, self::FIELD)
            : null;
    }

    public function attach(Entity $entity, ?StoredFile $letter): void
    {
        if (! $letter instanceof StoredFile) {
            return;
        }

        $this->attachments->attach($entity, self::LETTER, $letter);
        $entity->save();
    }

    /**
     * Removes a letter the failed edit left unattached; a failure after the commit keeps the letter the entity holds.
     */
    public function discardUnattached(Entity $entity, ?StoredFile $letter): void
    {
        if (! $letter instanceof StoredFile || Entity::query()->whereKey($entity->id)->where(self::LETTER->column(), $letter->id)->exists()) {
            return;
        }

        $this->remover->remove($letter);
    }
}
