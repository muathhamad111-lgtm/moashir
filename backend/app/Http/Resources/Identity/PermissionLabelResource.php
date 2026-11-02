<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Permission $resource
 */
final class PermissionLabelResource extends JsonResource
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
        ];
    }
}
