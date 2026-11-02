<?php

declare(strict_types=1);

namespace App\Http\Resources\Assessments;

use App\Data\Assessments\StoredEvidence;
use App\Support\Assessments\EvidencePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StoredEvidence $resource
 */
final class EvidenceFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return EvidencePresenter::item($this->resource->evidence, $this->resource->subs);
    }
}
