<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Assessments\UploadLimits;
use App\Data\Settings\PathSettingsView;
use App\Enums\Files\EvidenceFileType;
use App\Support\Assessments\PathPresenter;
use App\Support\Finance\PriceQuotePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PathSettingsView $resource
 */
final class PathSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $view = $this->resource;

        return [
            ...PathPresenter::of($view->path),
            'review_sla_days' => $view->reviewSlaDays,
            'review_sla_limits' => $view->slaLimits,
            'upload_limits' => $view->uploadLimits->toArray(),
            'upload_limit_bounds' => [
                'max_file_mb' => UploadLimits::MAX_FILE_MB,
                'max_files_per_sub' => UploadLimits::MAX_FILES_PER_SUB,
            ],
            'evidence_type_catalog' => EvidenceFileType::values(),
            'is_paid' => $view->isPaid,
            'price' => PriceQuotePresenter::present($view->price),
            'entity_texts' => $view->texts->toArray(),
        ];
    }
}
