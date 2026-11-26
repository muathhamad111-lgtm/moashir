<?php

declare(strict_types=1);

namespace App\Data\Paths;

use App\Data\Assessments\SubmissionProgress;
use App\Data\Periods\AccreditedPeriodView;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;

final readonly class AccreditedPathSummary
{
    public function __construct(
        public PathPricing $pricing,
        public AccreditedPeriodView $period,
        public ?Payment $awaitingPayment,
        public ?Assessment $openRequest,
        public ?SubmissionProgress $openProgress,
        public ?Assessment $lastResult,
        public ApplyReadiness $apply,
        public bool $isListed,
    ) {}
}
