<?php

declare(strict_types=1);

namespace App\Data\Certificates;

use App\Enums\Certificates\CertificateIssuanceMode;
use App\Support\ValueObjects\LevelThresholds;

final readonly class CertificateSettingsView
{
    public function __construct(
        public CertificateIssuanceMode $accreditedIssuanceMode,
        public LevelThresholds $levelThresholds,
    ) {}
}
