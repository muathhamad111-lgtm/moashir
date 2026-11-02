<?php

declare(strict_types=1);

namespace App\Enums\Notifications;

enum NotificationCode: string
{
    case StaffInvited = 'USR-01';
    case AccountLocked = 'USR-02';
    case AccountStatusChanged = 'USR-03';
    case PasswordResetRequested = 'USR-04';
    case SuperAdminEmailChanged = 'USR-05';
    case EntityRegistered = 'REG-01';
    case OtpCode = 'REG-02';
    case EntityProfileIncomplete = 'REG-03';
    case EntityCoreDataEdited = 'REG-04';
    case EntityDeactivated = 'REG-05';
    case EntityReactivated = 'REG-06';
    case EntityVerificationReminder = 'REG-07';
    case EntityEmailChangeRequested = 'REG-08';
    case EntityEmailChangeDecided = 'REG-09';
    case EntityDeletionChanged = 'REG-10';
    case SuperAdminLogin = 'SEC-01';
    case SuperAdminMfaFailed = 'SEC-02';
    case SuperAdminGrantChanged = 'SEC-03';
    case ReasonRequiredAction = 'SEC-04';
    case SuperAdminAssignmentRejected = 'SEC-05';
    case IntegrationSecretReplaced = 'SEC-06';
    case CriteriaMainCreated = 'STD-01';
    case CriteriaMainWeightChanged = 'STD-02';
    case CriteriaSubCreated = 'STD-03';
    case CriteriaGuidingPointsEnabled = 'STD-04';
    case CriteriaDirectEvaluationEnabled = 'STD-05';
    case CriteriaScaleChanged = 'STD-06';
    case CriteriaWeightMismatch = 'STD-07';
    case CriteriaEditedWithScope = 'STD-08';
    case SelfAssessmentStarted = 'SELF-01';
    case SelfAssessmentDraftSaved = 'SELF-02';
    case SelfAssessmentSubmitted = 'SELF-03';
    case SelfAssessmentResultAvailable = 'SELF-04';
    case AccreditedFormOpened = 'ACC-01';
    case AccreditedDraftSaved = 'ACC-02';
    case AccreditedSubmitted = 'ACC-03';
    case AccreditedAwaitingAssignment = 'ACC-04';
    case AccreditedAssigned = 'ACC-05';
    case AccreditedReviewStarted = 'ACC-06';
    case AccreditedCompletionRequested = 'ACC-07';
    case AccreditedCompleted = 'ACC-08';
    case AccreditedFeedbackAvailable = 'ACC-10';
    case AccreditedReviewOverdue = 'ACC-11';
    case AccreditedCompletionExpired = 'ACC-12';
    case AdvancedCycleOpened = 'ADV-01';
    case AdvancedDeadlineApproaching = 'ADV-02';
    case AdvancedConditionsRejected = 'ADV-03';
    case AdvancedSubmitted = 'ADV-04';
    case AdvancedAssigned = 'ADV-05';
    case AdvancedInitialAssessmentCompleted = 'ADV-06';
    case AdvancedQualified = 'ADV-07';
    case AdvancedNotQualified = 'ADV-08';
    case CommitteeAssigned = 'ADV-09';
    case CommitteeEvaluationStarted = 'ADV-10';
    case CommitteeEvaluationSubmitted = 'ADV-11';
    case CommitteeAllSubmitted = 'ADV-12';
    case CommitteeOverdue = 'ADV-13';
    case CommitteeAssessorDisabled = 'ADV-14';
    case CommitteeSubstituteAssigned = 'ADV-15';
    case ReturnedToCommittee = 'ADV-16';
    case CommitteeAverageApproved = 'ADV-17';
    case AwardResultsAnnounced = 'ADV-18';
    case RegionApproved = 'ADV-20';
    case QualifiedForNational = 'ADV-21';
    case NationalResultApproved = 'ADV-22';
    case RegionalResult = 'ADV-23';
    case NationalResult = 'ADV-24';
    case CommitteeSubstituteApproved = 'ADV-26';
    case CommitteeDiscussionOpened = 'ADV-29';
    case CommitteeDiscussionEntry = 'ADV-30';
    case AdvancedReturnedForCompletion = 'ADV-25';
    case AdvancedCompletionExpired = 'ADV-27';
    case AdvancedFilteringException = 'ADV-28';
    case AdvancedWithdrawn = 'ADV-31';
    case CycleSubmissionsClosed = 'CYC-01';
    case CycleClosed = 'CYC-02';
    case EvidenceBundleReady = 'EVD-01';
    case EvidenceRefused = 'EVD-02';
    case SmsSendFailed = 'INT-01';
    case SmsGatewayTestPassed = 'INT-02';
    case PaymentGatewayTestFailed = 'INT-03';
    case ExternalServiceDown = 'INT-04';
    case RequestProcessingFailed = 'INT-05';
    case AiProviderUpdated = 'AI-04';
    case AiProviderConnectionFailed = 'AI-05';
    case PaymentStarted = 'PAY-01';
    case PaymentSucceeded = 'PAY-02';
    case PaymentFailed = 'PAY-03';
    case PaymentRefundRecorded = 'PAY-04';
    case PaymentReceiptIssued = 'PAY-06';
    case PaymentOrderCancelled = 'PAY-07';
    case PaymentRefundRequired = 'PAY-08';
    case ProviderRefundReported = 'PAY-09';
    case FinanceExportReady = 'FIN-01';

    public function category(): NotificationCategory
    {
        return match ($this) {
            self::StaffInvited, self::PasswordResetRequested, self::OtpCode,
            self::CriteriaMainWeightChanged, self::CriteriaSubCreated, self::CriteriaGuidingPointsEnabled,
            self::CriteriaDirectEvaluationEnabled, self::EntityRegistered, self::EntityProfileIncomplete,
            self::EntityVerificationReminder, self::EntityEmailChangeRequested, self::PaymentStarted,
            self::PaymentOrderCancelled, self::PaymentRefundRequired, self::ProviderRefundReported,
            self::AccreditedFormOpened, self::AccreditedSubmitted, self::AccreditedAwaitingAssignment,
            self::AccreditedAssigned, self::AccreditedCompletionRequested, self::EvidenceRefused,
            self::AdvancedCycleOpened, self::AdvancedDeadlineApproaching, self::AdvancedSubmitted, self::AdvancedAssigned,
            self::AdvancedInitialAssessmentCompleted, self::AdvancedReturnedForCompletion, self::CommitteeAssigned,
            self::CommitteeAllSubmitted, self::CommitteeAssessorDisabled, self::CommitteeSubstituteAssigned, self::ReturnedToCommittee,
            self::QualifiedForNational, self::NationalResultApproved, self::CommitteeDiscussionOpened => NotificationCategory::ActionRequired,
            self::AccountStatusChanged, self::CriteriaMainCreated, self::CriteriaScaleChanged,
            self::CriteriaEditedWithScope, self::EntityCoreDataEdited, self::EntityReactivated,
            self::EntityEmailChangeDecided, self::SelfAssessmentStarted, self::SelfAssessmentDraftSaved,
            self::SelfAssessmentSubmitted, self::SelfAssessmentResultAvailable, self::SmsGatewayTestPassed,
            self::AiProviderUpdated, self::PaymentSucceeded, self::PaymentRefundRecorded, self::PaymentReceiptIssued,
            self::FinanceExportReady, self::AccreditedDraftSaved, self::AccreditedReviewStarted, self::AccreditedCompleted,
            self::AccreditedFeedbackAvailable, self::AccreditedCompletionExpired, self::EvidenceBundleReady,
            self::AdvancedConditionsRejected, self::AdvancedQualified, self::AdvancedNotQualified, self::AdvancedCompletionExpired,
            self::AdvancedWithdrawn, self::CycleSubmissionsClosed, self::CycleClosed, self::CommitteeEvaluationStarted,
            self::CommitteeEvaluationSubmitted, self::CommitteeAverageApproved, self::AwardResultsAnnounced, self::RegionApproved,
            self::RegionalResult, self::NationalResult, self::CommitteeSubstituteApproved, self::CommitteeDiscussionEntry => NotificationCategory::Informational,
            default => NotificationCategory::Alert,
        };
    }

    public function sendsMail(): bool
    {
        return in_array($this, [
            self::SmsSendFailed,
            self::PaymentGatewayTestFailed,
            self::ExternalServiceDown,
            self::AiProviderConnectionFailed,
        ], true);
    }
}
