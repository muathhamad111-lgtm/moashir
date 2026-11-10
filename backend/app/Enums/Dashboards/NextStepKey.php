<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

enum NextStepKey: string
{
    case SettlePayment = 'settle_payment';
    case CompleteRequest = 'complete_request';
    case FinishDraft = 'finish_draft';
    case TrackReview = 'track_review';
    case KitAction = 'kit_action';
    case KitOffer = 'kit_offer';
    case ApplyAccredited = 'apply_accredited';
    case ApplyAward = 'apply_award';
    case ContinueSelfDraft = 'continue_self_draft';
    case StartSelf = 'start_self';

    public function rank(): int
    {
        return match ($this) {
            self::SettlePayment => 1,
            self::CompleteRequest => 2,
            self::FinishDraft => 3,
            self::TrackReview => 4,
            self::KitAction => 5,
            self::KitOffer => 6,
            self::ApplyAccredited => 7,
            self::ApplyAward => 8,
            self::ContinueSelfDraft => 9,
            self::StartSelf => 10,
        };
    }
}
