<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum PermissionKey: string
{
    case DashboardView = 'dashboard.view';
    case NotificationsView = 'notifications.view';
    case NotificationsAuditView = 'notifications.audit.view';
    case DashboardExecutiveView = 'dashboard.executive.view';

    case EntityProfileView = 'entity.profile.view';
    case EntityProfileEdit = 'entity.profile.edit';
    case EntitiesView = 'entities.view';
    case EntitiesManage = 'entities.manage';
    case EntitiesDeactivate = 'entities.deactivate';
    case EntitiesReactivate = 'entities.reactivate';
    case EntitiesVerificationResend = 'entities.verification.resend';
    case EntitiesEmailChangeApprove = 'entities.email_change.approve';
    case EntitiesDelete = 'entities.delete';
    case EntitiesExport = 'entities.export';

    case PrivacyRequestsManage = 'privacy.requests.manage';

    case AssessmentCriteriaView = 'assessment.criteria.view';
    case AssessmentHistoryView = 'assessment.history.view';
    case AssessmentSelfManage = 'assessment.self.manage';
    case AssessmentAccreditedManage = 'assessment.accredited.manage';
    case AssessmentAdvancedManage = 'assessment.advanced.manage';
    case EvidenceManage = 'evidence.manage';
    case EvidenceView = 'evidence.view';

    case RequestsQueueView = 'requests.queue.view';
    case RequestsAssign = 'requests.assign';
    case RequestsAssignedView = 'requests.assigned.view';
    case RequestsReviewRead = 'requests.review.read';
    case RequestsCompletionReturn = 'requests.completion.return';
    case AssessmentScoreManage = 'assessment.score.manage';
    case AssessmentContextView = 'assessment.context.view';

    case AwardCyclesView = 'award.cycles.view';
    case AwardCyclesManage = 'award.cycles.manage';
    case AwardCommitteeManage = 'award.committee.manage';
    case AwardFilteringView = 'award.filtering.view';
    case AwardFilteringDecide = 'award.filtering.decide';
    case AwardCommitteeAssignedView = 'award.committee.assigned.view';
    case AwardCommitteeScore = 'award.committee.score';
    case AwardSecretaryReview = 'award.secretary.review';
    case AwardSecretaryDecide = 'award.secretary.decide';
    case AwardResultsView = 'award.results.view';
    case AwardResultsRank = 'award.results.rank';
    case AwardResultsApprove = 'award.results.approve';
    case AwardResultsAnnounce = 'award.results.announce';

    case ReportsFeedbackView = 'reports.feedback.view';
    case CertificatesOwnView = 'certificates.own.view';
    case CertificatesIssueAccredited = 'certificates.issue.accredited';
    case CertificatesManage = 'certificates.manage';

    case BagOwnView = 'bag.own.view';
    case BagOwnPurchase = 'bag.own.purchase';
    case BagRequestsView = 'bag.requests.view';
    case BagRequestsAssign = 'bag.requests.assign';
    case BagAnalysisManage = 'bag.analysis.manage';
    case BagPlanApprove = 'bag.plan.approve';
    case BagSubscriptionActivateFree = 'bag.subscription.activate_free';
    case BagAdminManage = 'bag.admin.manage';

    case CriteriaView = 'criteria.view';
    case CriteriaManage = 'criteria.manage';
    case CriteriaPublish = 'criteria.publish';

    case ContentHomeManage = 'content.home.manage';
    case ContentKpisManage = 'content.kpis.manage';
    case ContactMessagesManage = 'contact.messages.manage';

    case AiKbManage = 'ai.kb.manage';
    case AiProviderManage = 'ai.provider.manage';

    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case RolesMatrixView = 'roles.matrix.view';
    case RolesMatrixManage = 'roles.matrix.manage';
    case AuditView = 'audit.view';
    case AuditExport = 'audit.export';

    case SettingsGeneralManage = 'settings.general.manage';
    case SettingsBrandingManage = 'settings.branding.manage';
    case SettingsAuthManage = 'settings.auth.manage';
    case SettingsAssessmentManage = 'settings.assessment.manage';
    case SettingsCertificatesManage = 'settings.certificates.manage';
    case SettingsPaymentManage = 'settings.payment.manage';
    case SettingsSmsManage = 'settings.sms.manage';
    case SettingsEmailManage = 'settings.email.manage';
    case SettingsNotificationsManage = 'settings.notifications.manage';
    case MessagingCampaignsSend = 'messaging.campaigns.send';

    case PaymentsOwnView = 'payments.own.view';
    case PaymentsOwnCreate = 'payments.own.create';
    case FinanceOperationsView = 'finance.operations.view';
    case FinanceOperationsExport = 'finance.operations.export';
    case FinanceRefundsManage = 'finance.refunds.manage';
    case FinanceReceiptsManage = 'finance.receipts.manage';
    case FinanceTransfersReview = 'finance.transfers.review';

    private const array SYSTEM_ADMIN_ONLY = [
        self::SettingsAuthManage,
        self::SettingsBrandingManage,
        self::SettingsPaymentManage,
        self::SettingsSmsManage,
        self::SettingsEmailManage,
        self::AiProviderManage,
    ];

    private const array GRANTABLE = [
        'dashboard.executive.view',
        'entities.deactivate',
        'entities.reactivate',
        'entities.verification.resend',
        'entities.email_change.approve',
        'entities.delete',
        'privacy.requests.manage',
        'bag.plan.approve',
        'bag.subscription.activate_free',
        'messaging.campaigns.send',
        'contact.messages.manage',
        'finance.transfers.review',
    ];

    private const array NON_CONFIDENTIAL = [
        'dashboard.view',
        'notifications.view',
        'notifications.audit.view',
        'dashboard.executive.view',
        'assessment.criteria.view',
        'assessment.history.view',
        'assessment.self.manage',
        'assessment.accredited.manage',
        'assessment.advanced.manage',
        'evidence.manage',
        'award.cycles.view',
        'award.cycles.manage',
        'award.committee.assigned.view',
        'certificates.own.view',
        'certificates.issue.accredited',
        'certificates.manage',
        'bag.own.view',
        'bag.own.purchase',
        'criteria.view',
        'criteria.manage',
        'criteria.publish',
        'content.home.manage',
        'content.kpis.manage',
        'ai.kb.manage',
        'roles.matrix.view',
        'roles.matrix.manage',
        'settings.general.manage',
        'settings.branding.manage',
        'settings.auth.manage',
        'settings.assessment.manage',
        'settings.certificates.manage',
        'settings.notifications.manage',
        'payments.own.view',
        'payments.own.create',
    ];

    private const array MODULE_PREFIXES = [
        'dashboard.' => PermissionModule::Dashboard,
        'notifications.' => PermissionModule::Dashboard,
        'entity.' => PermissionModule::Entity,
        'entities.' => PermissionModule::Entity,
        'privacy.' => PermissionModule::Privacy,
        'assessment.score.' => PermissionModule::Requests,
        'assessment.context.' => PermissionModule::Requests,
        'assessment.' => PermissionModule::Assessment,
        'evidence.' => PermissionModule::Assessment,
        'requests.' => PermissionModule::Requests,
        'award.' => PermissionModule::Award,
        'reports.' => PermissionModule::Reports,
        'certificates.' => PermissionModule::Reports,
        'bag.' => PermissionModule::Bag,
        'criteria.' => PermissionModule::Criteria,
        'content.' => PermissionModule::Content,
        'contact.' => PermissionModule::Content,
        'ai.' => PermissionModule::Ai,
        'users.' => PermissionModule::Admin,
        'roles.' => PermissionModule::Admin,
        'audit.' => PermissionModule::Admin,
        'settings.' => PermissionModule::Settings,
        'messaging.' => PermissionModule::Settings,
        'payments.' => PermissionModule::Finance,
        'finance.' => PermissionModule::Finance,
    ];

    public function module(): PermissionModule
    {
        foreach (self::MODULE_PREFIXES as $prefix => $module) {
            if (str_starts_with($this->value, $prefix)) {
                return $module;
            }
        }

        return PermissionModule::Admin;
    }

    public function isGrantable(): bool
    {
        return in_array($this->value, self::GRANTABLE, true);
    }

    public function isConfidential(): bool
    {
        return ! in_array($this->value, self::NON_CONFIDENTIAL, true);
    }

    /**
     * @return list<RoleKey>
     */
    public function lockedToRoles(): array
    {
        return in_array($this, self::SYSTEM_ADMIN_ONLY, true) ? [RoleKey::SystemAdmin] : [];
    }

    public function isLocked(): bool
    {
        return $this->lockedToRoles() !== [];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
