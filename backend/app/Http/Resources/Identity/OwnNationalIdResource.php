<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Data\Identity\NationalIdStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property NationalIdStatus $resource
 */
final class OwnNationalIdResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['national_id_status' => $this->resource->state->value, 'national_id_masked' => $this->resource->masked];
    }
}
