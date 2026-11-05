<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Award\CommitteeEntityVisibility;
use App\Enums\Files\EvidenceFileType;
use App\Enums\Settings\SettingKey;

final readonly class AssessmentSettingsUpdate
{
    /**
     * @param  list<EvidenceFileType>|null  $evidenceTypes
     */
    public function __construct(
        public ?int $reviewSlaDays = null,
        public ?int $maxFileMegabytes = null,
        public ?array $evidenceTypes = null,
        public ?int $maxFilesPerSub = null,
        public ?string $reason = null,
        public ?AdvancedSettingsUpdate $advanced = null,
        public ?CommitteeEntityVisibility $committeeVisibility = null,
        public ?int $generalMaxFileMegabytes = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return array_filter([
            SettingKey::AccreditedReviewSlaDays->value => $this->reviewSlaDays,
            SettingKey::AccreditedUploadMaxFileMb->value => $this->maxFileMegabytes,
            SettingKey::AccreditedEvidenceAllowedTypes->value => $this->evidenceTypes === null
                ? null
                : array_map(static fn (EvidenceFileType $type): string => $type->value, $this->evidenceTypes),
            SettingKey::AccreditedEvidenceMaxFilesPerSub->value => $this->maxFilesPerSub,
            SettingKey::FilesMaxSizeMb->value => $this->generalMaxFileMegabytes,
            ...($this->advanced?->values() ?? []),
            SettingKey::CommitteeEntityDataVisibility->value => $this->committeeVisibility?->value,
        ], static fn (mixed $value): bool => $value !== null);
    }

    public function isEmpty(): bool
    {
        return $this->values() === [];
    }
}
