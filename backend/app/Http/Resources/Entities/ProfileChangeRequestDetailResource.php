<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Data\Entities\ProfileChangeDetail;
use App\Enums\Entities\EntityProfileField;
use App\Enums\Entities\ProfileChangeFile;
use App\Models\Files\StoredFile;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A change request with one row per item: the approved value, the proposed value and, once decided, its verdict.
 *
 * @property-read ProfileChangeDetail $resource
 */
final class ProfileChangeRequestDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $change = $this->resource->request;

        return [
            ...new ProfileChangeRequestResource($change)->toArray($request),
            'stale_fields' => $this->resource->staleFields,
            'changes' => [
                ...array_map($this->fieldRow(...), $this->fieldsOf($change->changed_fields)),
                ...array_map(fn (ProfileChangeFile $file): array => $this->fileRow($file, $request), $change->fileChanges()->changed()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fieldRow(EntityProfileField $field): array
    {
        $detail = $this->resource;
        $change = $detail->request;

        return [
            'item' => $field->value,
            'kind' => 'field',
            'section' => $field->section()->value,
            'label' => LocalizedText::fromTranslation($field->labelKey())->toArray(),
            'old_value' => $detail->places->present($field, $change->base_values[$field->value] ?? null),
            'new_value' => $detail->places->present($field, $change->proposed[$field->value] ?? null),
            'is_stale' => in_array($field->value, $detail->staleFields, true),
            'decision' => $change->decisions[$field->value] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fileRow(ProfileChangeFile $file, Request $request): array
    {
        $change = $this->resource->request;
        $isPending = $change->isPending();

        return [
            'item' => $file->value,
            'kind' => 'file',
            'section' => $file->section()?->value,
            'label' => LocalizedText::fromTranslation($file->labelKey())->toArray(),
            'old_value' => $isPending ? $this->fileValue($this->approvedFile($file), $request) : null,
            'new_value' => $isPending ? $this->fileValue($change->getRelationValue($file->pendingRelation()), $request) : null,
            'removes' => $change->fileChanges()->removes($file),
            'is_stale' => false,
            'decision' => $change->decisions[$file->value] ?? null,
        ];
    }

    /**
     * @param  list<string>  $fields
     * @return list<EntityProfileField>
     */
    private function fieldsOf(array $fields): array
    {
        return array_values(array_filter(array_map(EntityProfileField::tryFrom(...), $fields)));
    }

    private function approvedFile(ProfileChangeFile $file): ?StoredFile
    {
        $approved = $this->resource->request->entity->getRelationValue($file->approvedRelation());

        return $approved instanceof StoredFile ? $approved : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fileValue(mixed $file, Request $request): ?array
    {
        return $file instanceof StoredFile ? [
            ...new EntityLogoResource($file)->toArray($request),
            'name' => $file->original_name,
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
        ] : null;
    }
}
