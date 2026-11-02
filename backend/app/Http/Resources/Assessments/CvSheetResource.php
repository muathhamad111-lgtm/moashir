<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\CvSheet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read CvSheet $resource
 */
final class CvSheetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'assessment' => new AssessmentResource($this->resource->assessment)->toArray($request),
            'cv' => (object) $this->resource->bodiesBySubId(),
        ];
    }
}
