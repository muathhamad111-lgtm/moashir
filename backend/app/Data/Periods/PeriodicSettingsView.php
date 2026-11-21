<?php

declare(strict_types=1);

namespace App\Data\Periods;

use App\Models\Assessments\AssessmentPeriod;
use Carbon\CarbonInterface;

final readonly class PeriodicSettingsView
{
    /**
     * @param  list<AssessmentPeriod>  $upcoming
     */
    public function __construct(
        public PeriodicSettings $settings,
        public ?AssessmentPeriod $current,
        public array $upcoming,
        public ?CarbonInterface $updatedAt,
    ) {}
}
