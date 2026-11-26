<?php

declare(strict_types=1);

namespace App\Services\Certificates;

use App\Data\Certificates\CertificateSettingsView;
use App\Enums\Certificates\CertificateIssuanceMode;
use App\Enums\Settings\SettingKey;
use App\Services\Settings\SettingsStore;
use App\Support\ValueObjects\LevelThresholds;

final readonly class CertificateSettingsReader
{
    public function __construct(private SettingsStore $store) {}

    public function read(): CertificateSettingsView
    {
        return new CertificateSettingsView($this->accreditedIssuanceMode(), $this->thresholds());
    }

    public function thresholds(): LevelThresholds
    {
        $stored = $this->store->get(SettingKey::CertificateLevelThresholds);

        return is_array($stored) ? LevelThresholds::fromArray($stored) : LevelThresholds::defaults();
    }

    public function accreditedIssuanceMode(): CertificateIssuanceMode
    {
        $stored = $this->store->get(SettingKey::AccreditedCertificateIssuanceMode);

        return is_string($stored) ? CertificateIssuanceMode::tryFrom($stored) ?? CertificateIssuanceMode::Automatic : CertificateIssuanceMode::Automatic;
    }

    public function issuesAutomatically(): bool
    {
        return $this->accreditedIssuanceMode() === CertificateIssuanceMode::Automatic;
    }
}
