<?php

declare(strict_types=1);

namespace App\Enums\Settings;

use App\Enums\Assessments\AssessmentPath;
use LogicException;

enum PathSetting: string
{
    case ReviewSlaDays = 'review_sla_days';
    case UploadMaxFileMb = 'upload_max_file_mb';
    case EvidenceAllowedTypes = 'evidence_allowed_types';
    case EvidenceMaxFilesPerSub = 'evidence_max_files_per_sub';
    case EntityTexts = 'entity_texts';

    public function keyFor(AssessmentPath $path): SettingKey
    {
        return match ($path) {
            AssessmentPath::Accredited => $this->accreditedKey(),
            AssessmentPath::Advanced => $this->advancedKey(),
            AssessmentPath::SelfAssessment => throw new LogicException('The self assessment path has no path settings.'),
        };
    }

    /**
     * @return list<AssessmentPath>
     */
    public static function paths(): array
    {
        return AssessmentPath::reviewed();
    }

    /**
     * @return list<string>
     */
    public static function pathValues(): array
    {
        return array_map(static fn (AssessmentPath $path): string => $path->value, self::paths());
    }

    private function accreditedKey(): SettingKey
    {
        return match ($this) {
            self::ReviewSlaDays => SettingKey::AccreditedReviewSlaDays,
            self::UploadMaxFileMb => SettingKey::AccreditedUploadMaxFileMb,
            self::EvidenceAllowedTypes => SettingKey::AccreditedEvidenceAllowedTypes,
            self::EvidenceMaxFilesPerSub => SettingKey::AccreditedEvidenceMaxFilesPerSub,
            self::EntityTexts => SettingKey::AccreditedEntityTexts,
        };
    }

    private function advancedKey(): SettingKey
    {
        return match ($this) {
            self::ReviewSlaDays => SettingKey::AdvancedInitialReviewSlaDays,
            self::UploadMaxFileMb => SettingKey::AdvancedUploadMaxFileMb,
            self::EvidenceAllowedTypes => SettingKey::AdvancedEvidenceAllowedTypes,
            self::EvidenceMaxFilesPerSub => SettingKey::AdvancedEvidenceMaxFilesPerSub,
            self::EntityTexts => SettingKey::AdvancedEntityTexts,
        };
    }
}
