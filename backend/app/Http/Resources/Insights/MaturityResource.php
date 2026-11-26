<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use App\Data\Insights\MaturityReport;
use App\Support\Insights\MaturityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read MaturityReport $resource
 */
final class MaturityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return MaturityPresenter::present($this->resource);
    }
}
