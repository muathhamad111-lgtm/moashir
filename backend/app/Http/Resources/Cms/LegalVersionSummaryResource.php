<?php

declare(strict_types=1);

namespace App\Http\Resources\Cms;

use App\Models\Identity\TermsVersion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read TermsVersion $resource
 */
final class LegalVersionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fieldsOf($this->resource);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fieldsOf(TermsVersion $version): array
    {
        return [
            'id' => $version->id,
            'kind' => $version->kind,
            'version' => $version->version,
            'status' => $version->status()->value,
            'title' => $version->title?->toArray(),
            'effective_from' => $version->effective_from?->toDateString(),
            'published_at' => $version->published_at?->toIso8601String(),
            'created_at' => $version->created_at->toIso8601String(),
            'updated_at' => $version->updated_at->toIso8601String(),
        ];
    }
}
