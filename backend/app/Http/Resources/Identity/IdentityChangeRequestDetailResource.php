<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\IdentityChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read IdentityChangeRequest $resource
 */
final class IdentityChangeRequestDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...new IdentityChangeRequestResource($this->resource)->toArray($request),
            'requested_national_id' => $this->resource->isPending() ? $this->resource->new_national_id : null,
        ];
    }
}
