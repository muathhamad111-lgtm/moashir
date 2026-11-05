<?php

declare(strict_types=1);

namespace App\Data\Certificates;

use App\Support\ValueObjects\LevelThresholds;

final readonly class CertificateSettingsView
{
    public function __construct(
        public bool $accreditedIssuanceEnabled,
        public LevelThresholds $levelThresholds,
    ) {}
}
