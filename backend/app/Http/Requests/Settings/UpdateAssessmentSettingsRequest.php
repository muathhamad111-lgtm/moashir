<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Data\Assessments\UploadLimits;
use App\Data\Settings\AdvancedSettingsUpdate;
use App\Data\Settings\AssessmentSettingsUpdate;
use App\Enums\Award\CommitteeEntityVisibility;
use App\Enums\Files\EvidenceFileType;
use App\Services\Settings\AssessmentSettingsReader;
use App\Support\ValueObjects\Percent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateAssessmentSettingsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sla = app(AssessmentSettingsReader::class)->slaLimits();

        return [
            'accredited_review_sla_days' => ['sometimes', 'required', 'integer', 'between:'.$sla['min'].','.$sla['max']],
            'upload_max_file_mb' => ['sometimes', 'required', 'integer', 'between:1,'.UploadLimits::MAX_FILE_MB],
            'general_upload_max_file_mb' => ['sometimes', 'required', 'integer', 'between:1,'.UploadLimits::MAX_FILE_MB],
            'evidence_allowed_types' => ['sometimes', 'required', 'array', 'min:1', 'max:'.count(EvidenceFileType::cases())],
            'evidence_allowed_types.*' => ['required', 'string', 'distinct', Rule::enum(EvidenceFileType::class)],
            'evidence_max_files_per_sub' => ['sometimes', 'required', 'integer', 'between:1,'.UploadLimits::MAX_FILES_PER_SUB],
            'advanced_min_entity_age_years' => ['sometimes', 'required', 'integer', 'between:0,100'],
            'advanced_disqualification_score' => ['sometimes', 'required', 'numeric', 'between:0,100', 'regex:/^\d{1,3}(\.\d{1,2})?$/'],
            'advanced_initial_review_sla_days' => ['sometimes', 'required', 'integer', 'between:'.$sla['min'].','.$sla['max']],
            'committee_entity_data_visibility' => ['sometimes', 'required', Rule::enum(CommitteeEntityVisibility::class)],
            'reason' => ['nullable', 'string', 'min:10', 'max:1000'],
        ];
    }

    public function toUpdate(): AssessmentSettingsUpdate
    {
        return new AssessmentSettingsUpdate(
            $this->filled('accredited_review_sla_days') ? $this->integer('accredited_review_sla_days') : null,
            $this->filled('upload_max_file_mb') ? $this->integer('upload_max_file_mb') : null,
            $this->has('evidence_allowed_types') ? $this->types() : null,
            $this->filled('evidence_max_files_per_sub') ? $this->integer('evidence_max_files_per_sub') : null,
            $this->filled('reason') ? $this->string('reason')->trim()->toString() : null,
            new AdvancedSettingsUpdate(
                $this->filled('advanced_min_entity_age_years') ? $this->integer('advanced_min_entity_age_years') : null,
                $this->filled('advanced_disqualification_score') ? Percent::of($this->string('advanced_disqualification_score')->toString()) : null,
                $this->filled('advanced_initial_review_sla_days') ? $this->integer('advanced_initial_review_sla_days') : null,
            ),
            $this->enum('committee_entity_data_visibility', CommitteeEntityVisibility::class),
            $this->filled('general_upload_max_file_mb') ? $this->integer('general_upload_max_file_mb') : null,
        );
    }

    /**
     * @return list<EvidenceFileType>
     */
    private function types(): array
    {
        $types = [];

        foreach ((array) $this->input('evidence_allowed_types', []) as $value) {
            $type = is_string($value) ? EvidenceFileType::tryFrom($value) : null;

            if ($type instanceof EvidenceFileType) {
                $types[] = $type;
            }
        }

        return $types;
    }
}
