<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\Role;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Role $resource
 */
final class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->name,
            'label' => $this->resource->label,
            'description' => $this->resource->description,
            'assignable' => $this->resource->is_assignable,
            'cycle_scoped' => $this->resource->is_cycle_scoped,
        ];
    }
}
