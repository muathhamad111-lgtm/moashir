<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Entities\RegistrationDocuments;
use App\Enums\Entities\EntityDocument;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\FileIntake;
use App\Services\Files\StoredFileRemover;
use Throwable;

/**
 * Takes in the documents of a registration before the entity exists, attaches them to the new entity, or removes
 * them when the registration fails. A file left behind by a crash stays unattached and the orphan purge removes it.
 */
final readonly class EntityRegistrationDocuments
{
    public function __construct(
        private FileIntake $intake,
        private EntityDocumentAttachments $attachments,
        private StoredFileRemover $remover,
    ) {}

    /**
     * @return array<string, StoredFile>
     */
    public function accept(RegistrationDocuments $documents): array
    {
        $accepted = [];

        try {
            foreach (EntityDocument::required() as $document) {
                $accepted[$document->value] = $this->intake->accept($documents->uploadOf($document), $document->purpose(), null, $document->value);
            }
        } catch (Throwable $failure) {
            $this->discard($accepted);

            throw $failure;
        }

        return $accepted;
    }

    /**
     * @param  array<string, StoredFile>  $accepted
     */
    public function attachTo(Entity $entity, User $owner, array $accepted): void
    {
        foreach ($accepted as $value => $file) {
            $file->forceFill(['uploaded_by_user_id' => $owner->id]);
            $this->attachments->attach($entity, EntityDocument::from($value), $file);
        }

        $entity->save();
    }

    /**
     * @param  array<string, StoredFile>  $accepted
     */
    public function discard(array $accepted): void
    {
        foreach ($accepted as $file) {
            $this->remover->remove($file);
        }
    }
}
