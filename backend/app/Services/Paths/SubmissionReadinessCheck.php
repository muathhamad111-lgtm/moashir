<?php

declare(strict_types=1);

namespace App\Services\Paths;

use App\Data\Assessments\SubmissionProgress;
use App\Data\Paths\SubmissionReadiness;
use App\Data\Periods\AccreditedPeriodView;
use App\Enums\Assessments\AccreditedAssessmentStatus;
use App\Enums\Periods\SubmissionBlock;
use App\Models\Assessments\Assessment;

final class SubmissionReadinessCheck
{
    public function check(?Assessment $active, ?SubmissionProgress $progress, AccreditedPeriodView $period): SubmissionReadiness
    {
        $block = $this->blockOf($active, $progress);

        if ($block instanceof SubmissionBlock) {
            return new SubmissionReadiness($block);
        }

        return $period->isAssessed() ? new SubmissionReadiness(SubmissionBlock::PeriodAlreadyAssessed, $period->nextSubmissionFrom()) : new SubmissionReadiness;
    }

    private function blockOf(?Assessment $active, ?SubmissionProgress $progress): ?SubmissionBlock
    {
        return match (true) {
            ! $active instanceof Assessment => SubmissionBlock::NoOpenDraft,
            $active->status === AccreditedAssessmentStatus::AwaitingPayment => SubmissionBlock::AwaitingPayment,
            $active->status !== AccreditedAssessmentStatus::Draft => SubmissionBlock::NoOpenDraft,
            $active->isFrozen() => SubmissionBlock::EntityFrozen,
            $active->awaitsCriteriaUpdate() => SubmissionBlock::CriteriaUpdateInProgress,
            $progress instanceof SubmissionProgress && $progress->remainingCount() > 0 => SubmissionBlock::Incomplete,
            default => null,
        };
    }
}
