<?php

declare(strict_types=1);

namespace App\Data\Assessments;

use App\Data\Paths\ApplyReadiness;
use App\Data\Paths\PathPricing;
use App\Data\Paths\SubmissionReadiness;
use App\Data\Periods\AccreditedPeriodView;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;

final readonly class AccreditedCurrent
{
    public function __construct(
        public ?Payment $awaitingPayment,
        public ?Assessment $active,
        public ?Assessment $latestClosed,
        public ?SubmissionProgress $activeProgress,
        public AccreditedPeriodView $period,
        public SubmissionReadiness $submission,
        public ApplyReadiness $apply,
        public PathPricing $pricing,
    ) {}
}
