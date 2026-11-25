<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Data\Audit\AuditEntry;
use App\Enums\Audit\AuditEvent;
use App\Enums\Entities\EntityDocument;
use App\Models\Entities\Entity;
use App\Models\Files\StoredFile;
use App\Services\Audit\AuditRecorder;
use App\Services\Files\StoredFileRemover;
use Illuminate\Support\Facades\DB;

/**
 * Sets or clears the file of one of the entity's documents, audits the change and removes the file it replaces once
 * the transaction commits. The caller saves the entity.
 */
final readonly class EntityDocumentAttachments
{
    public function __construct(private StoredFileRemover $remover, private AuditRecorder $audit) {}

    public function attach(Entity $entity, EntityDocument $document, StoredFile $file): void
    {
        $previous = $this->currentFileOf($entity, $document);
        $file->attachable()->associate($entity)->save();
        $entity->forceFill([$document->column() => $file->id]);

        $this->audit->record(new AuditEntry(AuditEvent::EntityDocumentUploaded, $entity, ['document' => $document->value, 'file_id' => $previous?->public_id], [
            'document' => $document->value,
            'file_id' => $file->public_id,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
            'sha256' => $file->sha256,
        ]));

        if ($previous instanceof StoredFile && $previous->id !== $file->id) {
            $this->release($previous);
        }
    }

    public function detach(Entity $entity, EntityDocument $document): void
    {
        $current = $this->currentFileOf($entity, $document);

        if (! $current instanceof StoredFile) {
            return;
        }

        $entity->forceFill([$document->column() => null]);

        $this->audit->record(new AuditEntry(AuditEvent::EntityDocumentRemoved, $entity, ['document' => $document->value, 'file_id' => $current->public_id], ['document' => $document->value, 'file_id' => null]));

        $this->release($current);
    }

    private function currentFileOf(Entity $entity, EntityDocument $document): ?StoredFile
    {
        $fileId = $entity->getAttribute($document->column());

        return is_int($fileId) ? StoredFile::withTrashed()->find($fileId) : null;
    }

    private function release(StoredFile $file): void
    {
        DB::afterCommit(fn () => $this->remover->remove($file));
    }
}
