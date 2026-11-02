<?php

declare(strict_types=1);

namespace App\Enums\Assessments;

enum AssessmentVerb: string
{
    case Start = 'start';
    case SaveDraft = 'save_draft';
    case Submit = 'submit';
    case ComputeResult = 'compute_result';
    case OpenDraft = 'open_draft';
    case Assign = 'assign';
    case Reassign = 'reassign';
    case StartReview = 'start_review';
    case ScoreReview = 'score_review';
    case RequestCompletion = 'request_completion';
    case Resubmit = 'resubmit';
    case ExpireCompletion = 'expire_completion';
    case Complete = 'complete';
    case Refund = 'refund';
    case OverrideScore = 'override_score';
    case Reopen = 'reopen';
    case ForceTransition = 'force_transition';
    case RejectConditions = 'reject_conditions';
    case ExpireDraft = 'expire_draft';
    case ReturnForCompletion = 'return_for_completion';
    case Withdraw = 'withdraw';
    case Qualify = 'qualify';
    case Disqualify = 'disqualify';
    case Distribute = 'distribute';
    case CompleteCommittee = 'complete_committee';
    case ResumeCommittee = 'resume_committee';
    case ReturnToCommittee = 'return_to_committee';
    case OpenDiscussion = 'open_discussion';
    case CloseDiscussion = 'close_discussion';
    case ApproveAverage = 'approve_average';
    case ApproveRegion = 'approve_region';
    case QualifyNational = 'qualify_national';
    case ApproveNational = 'approve_national';
    case UnapproveRegion = 'unapprove_region';
}
