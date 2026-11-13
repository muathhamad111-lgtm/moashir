<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum AwardIneligibility: string
{
    case NoCycle = 'no_cycle';
    case WindowNotOpen = 'window_not_open';
    case WindowClosed = 'window_closed';
    case RequestExists = 'request_exists';
    case AwaitingPayment = 'awaiting_payment';
    case PathUnavailable = 'path_unavailable';
    case MinEntityAge = 'min_entity_age';
    case Region = 'region';

    public static function ofRule(EligibilityRule $rule): self
    {
        return match ($rule) {
            EligibilityRule::MinEntityAge => self::MinEntityAge,
            EligibilityRule::RegionCovered => self::Region,
        };
    }
}
