<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Identity\TermsVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TermsVersion $resource
 */
final class LegalVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...LegalVersionSummaryResource::fieldsOf($this->resource),
            'body' => $this->resource->body->toArray(),
        ];
    }
}
