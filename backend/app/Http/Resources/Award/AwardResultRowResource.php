<?php

declare(strict_types=1);

namespace App\Http\Resources\Award;

use App\Models\Award\AwardRanking;
use App\Models\Entities\Region;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AwardRanking $resource
 */
final class AwardResultRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ranking = $this->resource;
        $assessment = $ranking->assessment;

        return [
            'assessment_id' => $assessment->id,
            'number' => $assessment->request_number,
            'status' => $assessment->status->value,
            'entity' => EntityPresenter::summary($assessment->entity),
            'region' => $assessment->region instanceof Region ? EntityPresenter::place($assessment->region) : null,
            'average_percent' => $ranking->average_percent->toDisplay(),
            'regional_rank' => $ranking->regional_rank,
            'national_rank' => $ranking->national_rank,
            'approved_at' => $ranking->created_at->toIso8601String(),
        ];
    }
}
