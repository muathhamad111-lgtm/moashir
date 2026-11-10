<?php

declare(strict_types=1);

namespace App\Enums\Periods;

enum SubmissionBlock: string
{
    case NoOpenDraft = 'no_open_draft';
    case AwaitingPayment = 'awaiting_payment';
    case EntityFrozen = 'entity_frozen';
    case CriteriaUpdateInProgress = 'criteria_update_in_progress';
    case Incomplete = 'incomplete';
    case PeriodAlreadyAssessed = 'period_already_assessed';
}
