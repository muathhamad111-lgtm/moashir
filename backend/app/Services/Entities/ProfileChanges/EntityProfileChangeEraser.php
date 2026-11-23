<?php

declare(strict_types=1);

namespace App\Services\Entities\ProfileChanges;

use App\Contracts\Entities\EntityErasureStep;
use App\Enums\Entities\ProfileChangeStatus;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Files\StoredFile;
use App\Services\Files\StoredFileRemover;

/**
 * Removes the files an erased entity uploaded and that still wait for approval; approved files are the entity's own
 * logo or certificate, handled by their erasers. The requests are deleted with the profile history that references them.
 */
final readonly class EntityProfileChangeEraser implements EntityErasureStep
{
    public function __construct(private StoredFileRemover $remover) {}

    public function erase(Entity $entity): void
    {
        $fileIds = EntityProfileChangeRequest::query()
            ->where('entity_id', $entity->id)
            ->where('status', ProfileChangeStatus::Pending)
            ->get()
            ->flatMap(static fn (EntityProfileChangeRequest $request): array => $request->fileChanges()->pendingIds())
            ->all();

        StoredFile::query()->whereKey($fileIds)->get()->each(fn (StoredFile $file) => $this->remover->remove($file));
    }
}
