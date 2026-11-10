<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Enums\Identity\PermissionKey as P;
use App\Enums\Identity\RoleKey;

final class DefaultPermissionMatrix
{
    private const array EVERY_ROLE = [P::DashboardView, P::NotificationsView];

    /**
     * @return list<P>
     */
    public function for(RoleKey $role): array
    {
        return [...self::EVERY_ROLE, ...$this->roleSpecific($role)];
    }

    /**
     * @return list<P>
     */
    private function roleSpecific(RoleKey $role): array
    {
        return match ($role) {
            RoleKey::SystemAdmin => $this->systemAdmin(),
            RoleKey::CriteriaOfficer => [P::AssessmentCriteriaView, P::CriteriaView, P::CriteriaManage, P::CriteriaPublish],
            RoleKey::RequestFollower => $this->requestFollower(),
            RoleKey::Assessor => $this->assessor(),
            RoleKey::FilteringOfficer => $this->filteringOfficer(),
            RoleKey::AwardSecretary => $this->awardSecretary(),
            RoleKey::ContentOfficer => [
                P::AssessmentCriteriaView, P::ContentHomeManage, P::ContentKpisManage, P::ContentInsightsManage,
                P::ContactMessagesManage, P::AiKbManage,
            ],
            RoleKey::FinanceOfficer => [
                P::EntitiesView, P::BagRequestsView, P::FinanceOperationsView, P::FinanceOperationsExport,
                P::FinanceRefundsManage, P::FinanceReceiptsManage, P::FinanceTransfersReview,
            ],
            RoleKey::Entity => $this->entity(),
        };
    }

    /**
     * @return list<P>
     */
    private function systemAdmin(): array
    {
        return [
            P::NotificationsAuditView, P::DashboardExecutiveView,
            P::EntitiesView, P::EntitiesManage, P::EntitiesDeactivate, P::EntitiesReactivate,
            P::EntitiesVerificationResend, P::EntitiesEmailChangeApprove, P::EntitiesDelete, P::EntitiesExport,
            P::PrivacyRequestsManage,
            P::AssessmentCriteriaView, P::AssessmentHistoryView, P::EvidenceView,
            P::RequestsQueueView, P::RequestsAssign, P::RequestsReviewRead, P::RequestsCompletionReturn,
            P::AssessmentContextView,
            P::AwardCyclesView, P::AwardCyclesManage, P::AwardCommitteeManage, P::AwardFilteringView,
            P::AwardSecretaryReview, P::AwardResultsView, P::AwardResultsAnnounce,
            P::ReportsFeedbackView, P::ReportsInsightsView, P::CertificatesManage,
            P::BagRequestsView, P::BagRequestsAssign, P::BagPlanApprove, P::BagSubscriptionActivateFree, P::BagAdminManage,
            P::CriteriaView, P::CriteriaPublish,
            P::ContentHomeManage, P::ContentKpisManage, P::ContentInsightsManage, P::ContactMessagesManage,
            P::AiKbManage, P::AiProviderManage,
            P::UsersView, P::UsersManage, P::RolesMatrixView, P::RolesMatrixManage, P::AuditView, P::AuditExport,
            P::SettingsGeneralManage, P::SettingsBrandingManage, P::SettingsAuthManage, P::SettingsAssessmentManage,
            P::SettingsCertificatesManage, P::SettingsPaymentManage, P::SettingsSmsManage, P::SettingsEmailManage,
            P::SettingsNotificationsManage, P::SettingsPricesManage, P::MessagingCampaignsSend,
            P::FinanceOperationsView, P::FinanceOperationsExport, P::FinanceRefundsManage,
        ];
    }

    /**
     * @return list<P>
     */
    private function requestFollower(): array
    {
        return [
            P::NotificationsAuditView, P::EntitiesView, P::AssessmentCriteriaView, P::AssessmentHistoryView, P::EvidenceView,
            P::RequestsQueueView, P::RequestsAssign, P::RequestsReviewRead, P::RequestsCompletionReturn,
            P::AwardCyclesView, P::AwardCommitteeManage, P::ReportsFeedbackView, P::ReportsInsightsView,
            P::BagRequestsView, P::BagRequestsAssign,
        ];
    }

    /**
     * @return list<P>
     */
    private function assessor(): array
    {
        return [
            P::EntitiesView, P::AssessmentCriteriaView, P::AssessmentHistoryView, P::EvidenceView,
            P::RequestsAssignedView, P::AssessmentScoreManage, P::AssessmentContextView,
            P::AwardCyclesView, P::AwardCommitteeAssignedView, P::AwardCommitteeScore,
            P::ReportsFeedbackView, P::CertificatesIssueAccredited, P::BagAnalysisManage,
        ];
    }

    /**
     * @return list<P>
     */
    private function filteringOfficer(): array
    {
        return [
            P::EntitiesView, P::AssessmentCriteriaView, P::AssessmentHistoryView, P::EvidenceView, P::AssessmentContextView,
            P::AwardCyclesView, P::AwardCommitteeManage, P::AwardFilteringView, P::AwardFilteringDecide,
            P::ReportsFeedbackView,
        ];
    }

    /**
     * @return list<P>
     */
    private function awardSecretary(): array
    {
        return [
            P::NotificationsAuditView, P::EntitiesView, P::AssessmentCriteriaView, P::AssessmentHistoryView,
            P::EvidenceView, P::AssessmentContextView,
            P::AwardCyclesView, P::AwardCyclesManage, P::AwardCommitteeManage, P::AwardFilteringView,
            P::AwardSecretaryReview, P::AwardSecretaryDecide, P::AwardResultsView, P::AwardResultsRank,
            P::AwardResultsApprove, P::AwardResultsAnnounce, P::ReportsFeedbackView, P::CertificatesManage,
        ];
    }

    /**
     * @return list<P>
     */
    private function entity(): array
    {
        return [
            P::EntityProfileView, P::EntityProfileEdit, P::AssessmentCriteriaView, P::AssessmentHistoryView,
            P::AssessmentSelfManage, P::AssessmentAccreditedManage, P::AssessmentAdvancedManage,
            P::EvidenceManage, P::EvidenceView, P::AwardResultsView, P::ReportsFeedbackView, P::ReportsInsightsOwnView,
            P::CertificatesOwnView, P::BagOwnView, P::BagOwnPurchase, P::PaymentsOwnView, P::PaymentsOwnCreate,
        ];
    }
}
