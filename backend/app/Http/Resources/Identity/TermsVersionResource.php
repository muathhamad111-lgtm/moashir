<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\TermsVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TermsVersion $resource
 */
final class TermsVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'kind' => $this->resource->kind,
            'version' => $this->resource->version,
            'body' => $this->resource->body->toArray(),
            'effective_from' => $this->resource->effective_from?->toDateString(),
        ];
    }
}
