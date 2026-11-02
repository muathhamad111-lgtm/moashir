<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Enums\Files\EvidenceFileType;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ScalarValue;

final readonly class FileSettings
{
    private const int KILOBYTES_PER_MEGABYTE = 1024;

    public function __construct(private SettingsStore $settings) {}

    public function maxUploadKilobytes(): int
    {
        return $this->maxUploadMegabytes() * self::KILOBYTES_PER_MEGABYTE;
    }

    public function maxUploadMegabytes(): int
    {
        return max(1, ScalarValue::int($this->settings->get(SettingKey::FilesMaxSizeMb), ScalarValue::int(SettingKey::FilesMaxSizeMb->defaultValue())));
    }

    /**
     * @return list<EvidenceFileType>
     */
    public function evidenceTypes(): array
    {
        $stored = $this->settings->get(SettingKey::EvidenceAllowedTypes);
        $types = [];

        foreach (is_array($stored) ? $stored : [] as $value) {
            $type = is_string($value) ? EvidenceFileType::tryFrom($value) : null;

            if ($type instanceof EvidenceFileType) {
                $types[] = $type;
            }
        }

        return $types;
    }

    public function evidenceMaxFilesPerSub(): int
    {
        return max(1, ScalarValue::int($this->settings->get(SettingKey::EvidenceMaxFilesPerSub), ScalarValue::int(SettingKey::EvidenceMaxFilesPerSub->defaultValue())));
    }
}
