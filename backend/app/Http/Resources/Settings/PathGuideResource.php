<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Settings\PathSettingsView;
use App\Support\Assessments\PathPresenter;
use App\Support\Finance\PriceQuotePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PathSettingsView $resource
 */
final class PathGuideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            ...PathPresenter::of($view->path),
            'entity_texts' => $view->texts->toArray(),
            'review_sla_days' => $view->reviewSlaDays,
            'upload_limits' => $view->uploadLimits->toArray(),
            'is_paid' => $view->isPaid,
            'price' => PriceQuotePresenter::present($view->price),
        ];
    }
}
