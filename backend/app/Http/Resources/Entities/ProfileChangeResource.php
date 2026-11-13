<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Data\Entities\ProfileChangeEntry;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ProfileChangeEntry $resource
 */
final class ProfileChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entry = $this->resource;

        return [
            'id' => $entry->id,
            'field' => $entry->field->value,
            'section' => $entry->field->section()->value,
            'field_label' => LocalizedText::fromTranslation($entry->field->labelKey())->toArray(),
            'old_value' => $entry->oldValue,
            'new_value' => $entry->newValue,
            'changed_by' => $entry->changedBy->value,
            'changed_at' => $entry->changedAt->toIso8601String(),
        ];
    }
}
