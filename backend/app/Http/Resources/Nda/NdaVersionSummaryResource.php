<?php

declare(strict_types=1);

namespace App\Http\Resources\Nda;

use App\Http\Resources\Cms\LegalVersionSummaryResource;
use App\Models\Identity\TermsVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TermsVersion $resource
 */
final class NdaVersionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...LegalVersionSummaryResource::fieldsOf($this->resource),
            'change_summary' => $this->resource->change_summary?->toArray(),
            'acceptances_count' => $this->resource->acceptances_count,
            'published_by' => $this->resource->relationLoaded('publishedBy') ? $this->resource->publishedBy?->name : null,
        ];
    }
}
