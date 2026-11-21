<?php

declare(strict_types=1);

namespace App\Http\Resources\Insights;

use App\Data\Insights\ReportLibraryRow;
use App\Support\Insights\ReportLibraryPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read ReportLibraryRow $resource
 */
final class ReportLibraryRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ReportLibraryPresenter::present($this->resource);
    }
}
