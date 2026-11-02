<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Models\Assessments\EvidenceBundle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Date;

/**
 * @property-read EvidenceBundle $resource
 */
final class EvidenceBundleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $bundle = $this->resource;

        return [
            'id' => $bundle->public_id,
            'assessment_id' => $bundle->assessment_id,
            'status' => $bundle->status->value,
            'file_count' => $bundle->file_count,
            'size_bytes' => $bundle->size_bytes,
            'is_downloadable' => $bundle->isDownloadable(Date::now()),
            'requested_at' => $bundle->created_at->toIso8601String(),
            'ready_at' => $bundle->ready_at?->toIso8601String(),
            'expires_at' => $bundle->expires_at?->toIso8601String(),
        ];
    }
}
