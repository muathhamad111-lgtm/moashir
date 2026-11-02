<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\FeedbackView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FeedbackView $resource
 */
final class FeedbackViewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'assessment' => new AssessmentResource($this->resource->assessment)->toArray($request),
            'feedback' => (object) $this->resource->bySubId(),
        ];
    }
}
