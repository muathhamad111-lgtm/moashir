<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Enums\Entities\ProfileChangeFile;
use App\Models\Entities\EntityProfileChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read EntityProfileChangeRequest $resource
 */
final class ProfileChangeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $change = $this->resource;
        $entity = $change->entity;

        return [
            'id' => $change->public_id,
            'status' => $change->status->value,
            'revision' => $change->revision,
            'entity' => ['id' => $entity->id, 'reference' => $entity->reference(), 'name' => $entity->name->toArray()],
            'items' => $change->items(),
            'changed_fields' => $change->changed_fields,
            'files' => array_map(static fn (ProfileChangeFile $file): string => $file->value, $change->fileChanges()->changed()),
            'decisions' => $change->decisions,
            'review_note' => $change->review_note,
            'submitted_by' => $change->submitter?->name,
            'reviewed_by' => $change->reviewer?->name,
            'submitted_at' => $change->created_at->toIso8601String(),
            'updated_at' => $change->updated_at->toIso8601String(),
            'decided_at' => $change->decided_at?->toIso8601String(),
        ];
    }
}
