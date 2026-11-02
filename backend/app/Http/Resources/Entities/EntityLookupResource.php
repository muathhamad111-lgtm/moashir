<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Entities\Entity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Entity $resource
 */
final class EntityLookupResource extends JsonResource
{
    /**
     * @return array{id: int, reference: string, name: array{ar: string, en: string|null}, is_deleted: bool}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'reference' => $this->resource->reference(),
            'name' => $this->resource->name->toArray(),
            'is_deleted' => $this->resource->trashed(),
        ];
    }
}
