<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Paths\ApplyReadiness;
use App\Data\Paths\PathPricing;
use App\Data\Periods\AccreditedPeriodView;
use App\Enums\Periods\ApplyBlock;
use App\Enums\Periods\SubmissionBlock;
use App\Models\Assessments\Assessment;
use App\Models\Finance\Payment;

final class ApplyReadinessCheck
{
    public function check(?Payment $awaitingPayment, ?Assessment $active, PathPricing $pricing, AccreditedPeriodView $period): ApplyReadiness
    {
        $block = match (true) {
            $active instanceof Assessment => ApplyBlock::OpenRequestExists,
            $awaitingPayment instanceof Payment => ApplyBlock::AwaitingPayment,
            ! $pricing->isAvailable() => ApplyBlock::PathUnavailable,
            default => null,
        };

        return new ApplyReadiness($block, $period->isAssessed() ? SubmissionBlock::PeriodAlreadyAssessed : null);
    }
}
