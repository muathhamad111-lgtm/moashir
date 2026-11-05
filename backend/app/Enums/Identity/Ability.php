<?php

declare(strict_types=1);

namespace App\Enums\Identity;

enum Ability: string
{
    case Authenticated = 'identity.authenticated';
    case SuperAdminSelfService = 'identity.super-admin.self-service';
    case EnrollMfa = 'identity.mfa.enroll';
    case ListRoles = 'identity.roles.list';
    case GlobalSearch = 'search.global';
    case IntegrationsOverview = 'settings.integrations.view';
    case ManagePaymentIntegration = 'settings.integrations.payment';
    case ManageSmsIntegration = 'settings.integrations.sms';
    case ManageEmailIntegration = 'settings.integrations.email';
    case ManageAiIntegration = 'settings.integrations.ai';
    case ManageFinanceSettings = 'settings.finance.manage';
    case EditFinanceSettingField = 'settings.finance.edit-field';
    case ManageAssessmentSettings = 'settings.assessment-section.manage';
    case ManageCertificateSettings = 'settings.certificates-section.manage';
    case ManageContactSettings = 'settings.contact-section.manage';
    case ManageAuthSettings = 'settings.auth-section.manage';
    case ViewReadinessDetails = 'platform.readiness.details';
    case IssueAccreditedCertificate = 'certificates.accredited-certificate.issue';
    case ViewFeedbackReport = 'reports.feedback-report.view';
    case ViewCommitteeRoster = 'award.committee-roster.view';
    case ManageCommitteeRoster = 'award.committee-roster.manage';
    case ManageCommitteeMember = 'award.committee-roster.member';
    case ViewOwnCommitteeWork = 'award.committee-work.view';
    case ReviewSecretaryDesk = 'award.secretary-desk.review';
    case DecideSecretaryDesk = 'award.secretary-desk.decide';
    case ViewSecretaryEvaluation = 'award.secretary-desk.evaluation';
    case ApproveSubstitution = 'award.secretary-desk.substitution';
    case CloseCommitteeDiscussion = 'award.secretary-desk.discussion';
    case ViewAwardResults = 'award.results-board.view';
    case RankAwardResult = 'award.results-board.rank';
    case ApproveRegionResults = 'award.results-board.approve-region';
    case UnapproveRegionResults = 'award.results-board.unapprove-region';
    case ApproveNationalResults = 'award.results-board.approve-national';
    case AnnounceAwardResults = 'award.results-board.announce';
    case ViewDashboard = 'dashboard.summary.view';
}
