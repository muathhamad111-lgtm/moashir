<?php

declare(strict_types=1);

namespace App\Http\Resources\Reports;

use App\Data\Reports\FeedbackReport;
use App\Support\Reports\FeedbackReportPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read FeedbackReport $resource
 */
final class FeedbackReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return FeedbackReportPresenter::present($this->resource, config()->string('app.display_timezone'));
    }
}
