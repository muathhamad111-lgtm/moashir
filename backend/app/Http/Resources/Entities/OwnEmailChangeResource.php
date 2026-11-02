<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Identity\EmailChangeRequest;
use App\Support\ValueObjects\EmailAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read EmailChangeRequest $resource
 */
final class OwnEmailChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->resource->status->value,
            'policy' => $this->resource->policy->value,
            'new_email' => new EmailAddress($this->resource->new_email)->masked(),
        ];
    }
}
