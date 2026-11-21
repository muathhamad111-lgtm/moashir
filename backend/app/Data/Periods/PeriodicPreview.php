<?php

declare(strict_types=1);

namespace App\Data\Periods;

use App\Models\Assessments\AssessmentPeriod;
use Carbon\CarbonImmutable;

final readonly class PeriodicPreview
{
    /**
     * @param  list<PlannedPeriod>  $upcoming
     * @param  list<AssessmentPeriod>  $removed
     */
    public function __construct(
        public PeriodicSettings $settings,
        public ?AssessmentPeriod $current,
        public ?CarbonImmutable $cadenceEffectiveFrom,
        public array $upcoming,
        public array $removed,
        public int $correctionsToSend,
    ) {}
}
