<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Permission $resource
 */
final class PermissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->resource->name,
            'module' => $this->resource->module->value,
            'label' => $this->resource->label,
            'description' => $this->resource->description,
            'system' => $this->resource->is_system,
            'locked_to_roles' => $this->resource->locked_to_roles,
            'confidential' => $this->resource->is_confidential,
            'grantable' => $this->resource->is_grantable,
        ];
    }
}
