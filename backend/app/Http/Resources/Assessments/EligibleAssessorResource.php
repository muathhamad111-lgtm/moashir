<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\EligibleAssessor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read EligibleAssessor $resource
 */
final class EligibleAssessorResource extends JsonResource
{
    /**
     * @return array{id: int, name: string, open_requests: int}
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource->id, 'name' => $this->resource->name, 'open_requests' => $this->resource->openRequests];
    }
}
