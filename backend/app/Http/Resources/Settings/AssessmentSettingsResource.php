<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Settings\AssessmentSettingsView;
use App\Enums\Files\EvidenceFileType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read AssessmentSettingsView $resource
 */
final class AssessmentSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $limits = $this->resource->uploadLimits->toArray();

        return [
            'accredited_review_sla_days' => $this->resource->reviewSlaDays,
            'accredited_review_sla_limits' => $this->resource->slaLimits,
            'upload_max_file_mb' => $limits['max_file_mb'],
            'evidence_allowed_types' => $limits['allowed_types'],
            'evidence_max_files_per_sub' => $limits['max_files_per_sub'],
            'evidence_type_catalog' => EvidenceFileType::values(),
            'general_upload_max_file_mb' => $this->resource->generalMaxFileMegabytes,
            'advanced_min_entity_age_years' => $this->resource->advancedDefaults->minEntityAgeYears,
            'advanced_disqualification_score' => $this->resource->advancedDefaults->disqualificationScore->toDisplay(),
            'advanced_initial_review_sla_days' => $this->resource->advancedDefaults->initialReviewSlaDays,
            'committee_entity_data_visibility' => $this->resource->committeeVisibility->value,
        ];
    }
}
