<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Data\Assessments\UploadLimits;
use App\Data\Award\CycleDefaults;
use App\Enums\Award\CommitteeEntityVisibility;

final readonly class AssessmentSettingsView
{
    /**
     * @param  array{min: int, max: int}  $slaLimits
     */
    public function __construct(
        public int $reviewSlaDays,
        public array $slaLimits,
        public UploadLimits $uploadLimits,
        public CycleDefaults $advancedDefaults,
        public CommitteeEntityVisibility $committeeVisibility,
    ) {}
}
