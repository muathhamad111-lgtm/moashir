<?php

declare(strict_types=1);

namespace App\Services\Periods;

use App\Data\Periods\AccreditedPeriodView;
use App\Enums\Assessments\AssessmentPath;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\AssessmentPeriod;
use App\Models\Entities\Entity;

final readonly class AccreditedPeriodSummary
{
    public function __construct(
        private PeriodicSettingsReader $settings,
        private PeriodLocator $periods,
        private PeriodStateResolver $resolver,
    ) {}

    public function of(Entity $entity): AccreditedPeriodView
    {
        $settings = $this->settings->read();
        $current = $this->periods->current();

        if (! $current instanceof AssessmentPeriod) {
            return new AccreditedPeriodView($settings, null, null, $this->periods->firstUpcoming());
        }

        $requests = Assessment::query()
            ->ownedBy($entity->id)
            ->ofPath(AssessmentPath::Accredited)
            ->where('period_id', $current->id)
            ->latest('id')
            ->get();

        return new AccreditedPeriodView(
            $settings,
            $current,
            $this->resolver->resolve($entity, $current, $requests, false, $settings->largestReminderDays()),
            $this->periods->after($current),
        );
    }
}
