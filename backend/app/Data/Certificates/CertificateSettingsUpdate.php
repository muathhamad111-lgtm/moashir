<?php

declare(strict_types=1);

namespace App\Data\Certificates;

use App\Enums\Settings\SettingKey;
use App\Support\ValueObjects\LevelThresholds;

final readonly class CertificateSettingsUpdate
{
    public function __construct(
        public ?bool $accreditedIssuanceEnabled,
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

        if ($this->accreditedIssuanceEnabled !== null) {
            $values[SettingKey::AccreditedCertificateIssuance->value] = $this->accreditedIssuanceEnabled;
        }

        if ($this->levelThresholds instanceof LevelThresholds) {
            $values[SettingKey::CertificateLevelThresholds->value] = $this->levelThresholds->toArray();
        }

        return $values;
    }
}
