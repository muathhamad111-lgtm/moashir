<?php

declare(strict_types=1);

namespace App\Data\Periods;

use App\Enums\Periods\EntityPeriodState;
use App\Enums\Periods\PeriodCadence;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use Carbon\CarbonImmutable;

final readonly class AccreditedPeriodView
{
    public function __construct(
        public PeriodicSettings $settings,
        public ?AssessmentPeriod $current,
        public ?ResolvedPeriodState $state,
        public ?AssessmentPeriod $next,
    ) {}

    public function isLive(): bool
    {
        return $this->current instanceof AssessmentPeriod;
    }

    public function cadence(): PeriodCadence
    {
        return $this->settings->cadence;
    }

    public function stateValue(): ?EntityPeriodState
    {
        return $this->state?->state;
    }

    public function periodRequest(): ?Assessment
    {
        return $this->state?->request;
    }

    public function isAssessed(): bool
    {
        return $this->stateValue() === EntityPeriodState::Assessed;
    }

    public function cadenceEffectiveFrom(): ?CarbonImmutable
    {
        if (! $this->current instanceof AssessmentPeriod || $this->current->cadence === $this->settings->cadence) {
            return null;
        }

        return $this->current->ends_on->addDay();
    }

    public function nextSubmissionFrom(): ?CarbonImmutable
    {
        return $this->isAssessed() ? ($this->next->starts_on ?? $this->current?->ends_on->addDay()) : null;
    }
}
