<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use App\Data\Insights\ResultCandidate;
use App\Support\Insights\ResultRefPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ResultCandidate $resource
 */
final class ResultRefResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ResultRefPresenter::present($this->resource);
    }
}
