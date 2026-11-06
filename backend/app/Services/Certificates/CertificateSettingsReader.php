<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Data\Certificates\CertificateSettingsView;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ValueObjects\LevelThresholds;

final readonly class CertificateSettingsReader
{
    public function __construct(private SettingsStore $store) {}

    public function read(): CertificateSettingsView
    {
        return new CertificateSettingsView($this->accreditedIssuanceEnabled(), $this->thresholds());
    }

    public function thresholds(): LevelThresholds
    {
        $stored = $this->store->get(SettingKey::CertificateLevelThresholds);

        return is_array($stored) ? LevelThresholds::fromArray($stored) : LevelThresholds::defaults();
    }

    public function accreditedIssuanceEnabled(): bool
    {
        return $this->store->get(SettingKey::AccreditedCertificateIssuance) !== false;
    }
}
