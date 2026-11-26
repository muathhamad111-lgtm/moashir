<?php

declare(strict_types=1);

namespace App\Services\Entities;

use App\Contracts\Entities\EntityErasureStep;
use App\Enums\Entities\EntityDocument;
use App\Models\Entities\Entity;

/**
 * Removes the license certificate and the authorization letter of an erased entity; the VAT certificate has its own
 * eraser with the billing data.
 */
final readonly class EntityDocumentsEraser implements EntityErasureStep
{
    public function __construct(private EntityDocumentAttachments $documents) {}

    public function erase(Entity $entity): void
    {
        foreach (EntityDocument::required() as $document) {
            $this->documents->detach($entity, $document);
        }

        $entity->save();
    }
}
