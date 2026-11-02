<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'roles' => array_map(static fn (RoleKey $role): string => $role->value, $this->resource->roleKeys()),
            'status' => $this->resource->effectiveStatus()->value,
            'mfa_enabled' => $this->resource->hasConfirmedTotp(),
            'last_login_at' => $this->resource->last_login_at?->toIso8601String(),
            'entity_id' => $this->resource->entity_id,
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
