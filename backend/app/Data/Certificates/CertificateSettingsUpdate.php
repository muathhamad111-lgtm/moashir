<?php

declare(strict_types=1);

namespace App\Data\Certificates;

use App\Enums\Certificates\CertificateIssuanceMode;
use App\Enums\Settings\SettingKey;
use App\Support\ValueObjects\LevelThresholds;

final readonly class CertificateSettingsUpdate
{
    public function __construct(
        public ?CertificateIssuanceMode $accreditedIssuanceMode,
        public ?LevelThresholds $levelThresholds,
        public ?string $reason,
    ) {}

    public function isEmpty(): bool
    {
        return $this->values() === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $values = [];

        if ($this->accreditedIssuanceMode instanceof CertificateIssuanceMode) {
            $values[SettingKey::AccreditedCertificateIssuanceMode->value] = $this->accreditedIssuanceMode->value;
        }

        if ($this->levelThresholds instanceof LevelThresholds) {
            $values[SettingKey::CertificateLevelThresholds->value] = $this->levelThresholds->toArray();
        }

        return $values;
    }
}
