<?php

declare(strict_types=1);

namespace App\Services\Files;

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
}
