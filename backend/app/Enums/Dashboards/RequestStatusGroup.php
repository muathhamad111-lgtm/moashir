<?php

declare(strict_types=1);

namespace App\Enums\Dashboards;

use App\Enums\Assessments\AccreditedAssessmentStatus as Accredited;
use App\Enums\Assessments\AdvancedAssessmentStatus as Advanced;
use App\Enums\Assessments\AssessmentPath;
use App\Enums\Assessments\SelfAssessmentStatus as SelfStatus;

enum RequestStatusGroup: string
{
    case AwaitingPayment = 'awaiting_payment';
    case InQueue = 'in_queue';
    case UnderReview = 'under_review';
    case NeedsCompletion = 'needs_completion';
    case Completed = 'completed';
    case Declined = 'declined';

    private const array AWAITING_PAYMENT = [Accredited::AwaitingPayment->value, Accredited::Draft->value];

    private const array UNDER_REVIEW = [
        Accredited::Assigned->value, Accredited::UnderReview->value, Advanced::UnderInitialAssessment->value,
        Advanced::AwaitingFiltering->value, Advanced::QualifiedForCommittee->value, Advanced::UnderCommitteeAssessment->value,
        Advanced::CommitteeDiscussion->value, Advanced::AwaitingSecretaryApproval->value, Advanced::ReturnedToCommittee->value,
    ];

    private const array DECLINED = [
        Accredited::Refunded->value, Accredited::DeclinedIncomplete->value, Advanced::Withdrawn->value,
        Advanced::Expired->value, Advanced::RejectedConditions->value,
    ];

    private const array COMPLETED_SELF = [SelfStatus::Submitted->value, SelfStatus::ResultAvailable->value];

    private const array COMPLETED_ADVANCED = [
        Advanced::Approved->value, Advanced::RegionalWinner->value, Advanced::NonWinner->value, Advanced::QualifiedForNational->value,
        Advanced::NationalWinner->value, Advanced::NationalNonWinner->value, Advanced::NotQualified->value,
    ];

    public static function of(AssessmentPath $path, string $status): ?self
    {
        foreach (self::cases() as $group) {
            if (in_array($status, $group->statusesOn($path), true)) {
                return $group;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function statusesOn(AssessmentPath $path): array
    {
        return match ($this) {
            self::AwaitingPayment => self::AWAITING_PAYMENT,
            self::InQueue => $this->queuedOn($path),
            self::UnderReview => self::UNDER_REVIEW,
            self::NeedsCompletion => [Accredited::NeedsCompletion->value],
            self::Completed => $this->completedOn($path),
            self::Declined => self::DECLINED,
        };
    }

    /**
     * @return list<string>
     */
    private function queuedOn(AssessmentPath $path): array
    {
        return match ($path) {
            AssessmentPath::SelfAssessment => [],
            AssessmentPath::Accredited => [Accredited::Submitted->value],
            AssessmentPath::Advanced => [Advanced::AwaitingReviewAssignment->value],
        };
    }

    /**
     * @return list<string>
     */
    private function completedOn(AssessmentPath $path): array
    {
        return match ($path) {
            AssessmentPath::SelfAssessment => self::COMPLETED_SELF,
            AssessmentPath::Accredited => [Accredited::Completed->value],
            AssessmentPath::Advanced => self::COMPLETED_ADVANCED,
        };
    }
}
