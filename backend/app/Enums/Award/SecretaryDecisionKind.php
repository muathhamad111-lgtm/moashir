<?php

declare(strict_types=1);

namespace App\Enums\Award;

enum SecretaryDecisionKind: string
{
    case ApproveAverage = 'approve_average';
    case ReturnToCommittee = 'return_to_committee';
    case ApproveSubstitute = 'approve_substitute';
    case ApproveRegion = 'approve_region';
    case UnapproveRegion = 'unapprove_region';
    case ApproveNational = 'approve_national';
    case Announce = 'announce';
}
