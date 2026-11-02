<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\EvidenceListing;
use App\Support\Assessments\EvidencePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read EvidenceListing $resource
 */
final class EvidenceListResource extends JsonResource
{
    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return EvidencePresenter::list($this->resource->evidence, $this->resource->subs);
    }
}
