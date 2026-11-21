<?php

declare(strict_types=1);

namespace App\Enums\Insights;

enum InsightsEmptyReason: string
{
    case NoVerifiedResult = 'no_verified_result';
    case OnlySelf = 'only_self';
    case InProgress = 'in_progress';
    case UnderReview = 'under_review';

    public function callToAction(): ?InsightsCallToAction
    {
        return match ($this) {
            self::NoVerifiedResult, self::OnlySelf => InsightsCallToAction::StartAccredited,
            self::InProgress => InsightsCallToAction::ViewRequest,
            self::UnderReview => null,
        };
    }
}
