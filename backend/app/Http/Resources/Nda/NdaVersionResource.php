<?php

declare(strict_types=1);

namespace App\Http\Resources\Nda;

use App\Http\Resources\Cms\LegalVersionSummaryResource;
use App\Models\Identity\TermsVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An agreement version for its administrators: the raw editable text (ar and en) and the change summary.
 *
 * @property-read TermsVersion $resource
 */
final class NdaVersionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...LegalVersionSummaryResource::fieldsOf($this->resource),
            'change_summary' => $this->resource->change_summary?->toArray(),
            'body' => $this->resource->body->toArray(),
        ];
    }
}
