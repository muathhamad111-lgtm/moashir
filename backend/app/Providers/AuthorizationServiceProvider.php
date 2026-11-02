<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Identity\Ability;
use App\Enums\Identity\PermissionKey;
use App\Models\Assessments\Assessment;
use App\Models\Assessments\EvidenceBundle;
use App\Models\Assessments\EvidenceFile;
use App\Models\Award\AwardCycle;
use App\Models\Award\CommitteeAssignment;
use App\Models\Award\CommitteeDiscussion;
use App\Models\Criteria\CriteriaVersion;
use App\Models\Criteria\GuidingPoint;
use App\Models\Criteria\MainCriterion;
use App\Models\Criteria\SubCriterion;
use App\Models\Entities\City;
use App\Models\Entities\Entity;
use App\Models\Entities\PrivacyRequest;
use App\Models\Files\StoredFile;
use App\Models\Finance\CheckoutIntent;
use App\Models\Finance\FinanceExport;
use App\Models\Finance\Payment;
use App\Models\Finance\PaymentReceipt;
use App\Models\Finance\Refund;
use App\Models\Identity\EmailChangeRequest;
use App\Models\Identity\Permission;
use App\Models\Identity\Role;
use App\Models\Identity\User;
use App\Models\Identity\UserSession;
use App\Policies\Assessments\AssessmentPolicy;
use App\Policies\Assessments\EvidenceBundlePolicy;
use App\Policies\Assessments\EvidenceFilePolicy;
use App\Policies\Award\AwardCyclePolicy;
use App\Policies\Award\AwardResultsPolicy;
use App\Policies\Award\CommitteeAssignmentPolicy;
use App\Policies\Award\CommitteeDiscussionPolicy;
use App\Policies\Award\CommitteePolicy;
use App\Policies\Award\SecretaryDeskPolicy;
use App\Policies\Criteria\CriteriaNodePolicy;
use App\Policies\Criteria\CriteriaVersionPolicy;
use App\Policies\Entities\CityPolicy;
use App\Policies\Entities\EmailChangeRequestPolicy;
use App\Policies\Entities\EntityPolicy;
use App\Policies\Entities\PrivacyRequestPolicy;
use App\Policies\Files\StoredFilePolicy;
use App\Policies\Finance\CheckoutIntentPolicy;
use App\Policies\Finance\FinanceExportPolicy;
use App\Policies\Finance\PaymentPolicy;
use App\Policies\Finance\PaymentReceiptPolicy;
use App\Policies\Finance\RefundPolicy;
use App\Policies\Identity\PermissionPolicy;
use App\Policies\Identity\RolePolicy;
use App\Policies\Identity\UserPolicy;
use App\Policies\Identity\UserSessionPolicy;
use App\Policies\Integrations\IntegrationSettingsPolicy;
use App\Policies\Search\GlobalSearchPolicy;
use App\Policies\Settings\AssessmentSettingsPolicy;
use App\Policies\Settings\FinanceSettingsPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Horizon\Horizon;

final class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::before(static fn (User $user): ?bool => $user->isSuperAdmin() ? true : null);

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(UserSession::class, UserSessionPolicy::class);
        Gate::policy(CriteriaVersion::class, CriteriaVersionPolicy::class);
        Gate::policy(MainCriterion::class, CriteriaNodePolicy::class);
        Gate::policy(SubCriterion::class, CriteriaNodePolicy::class);
        Gate::policy(GuidingPoint::class, CriteriaNodePolicy::class);
        Gate::policy(Entity::class, EntityPolicy::class);
        Gate::policy(EmailChangeRequest::class, EmailChangeRequestPolicy::class);
        Gate::policy(PrivacyRequest::class, PrivacyRequestPolicy::class);
        Gate::policy(City::class, CityPolicy::class);
        Gate::policy(Assessment::class, AssessmentPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PaymentReceipt::class, PaymentReceiptPolicy::class);
        Gate::policy(Refund::class, RefundPolicy::class);
        Gate::policy(FinanceExport::class, FinanceExportPolicy::class);
        Gate::policy(CheckoutIntent::class, CheckoutIntentPolicy::class);
        Gate::policy(StoredFile::class, StoredFilePolicy::class);
        Gate::policy(EvidenceFile::class, EvidenceFilePolicy::class);
        Gate::policy(EvidenceBundle::class, EvidenceBundlePolicy::class);
        Gate::policy(AwardCycle::class, AwardCyclePolicy::class);
        Gate::policy(CommitteeAssignment::class, CommitteeAssignmentPolicy::class);
        Gate::policy(CommitteeDiscussion::class, CommitteeDiscussionPolicy::class);

        Gate::define(Ability::SuperAdminSelfService->value, static fn (User $user): bool => false);
        Gate::define(Ability::ListRoles->value, static fn (User $user): bool => $user->can(PermissionKey::UsersView->value)
            || $user->can(PermissionKey::RolesMatrixView->value));
        Gate::define(Ability::Authenticated->value, static fn (User $user): bool => true);
        Gate::define(Ability::GlobalSearch->value, [GlobalSearchPolicy::class, 'search']);
        Gate::define(Ability::IntegrationsOverview->value, [IntegrationSettingsPolicy::class, 'viewOverview']);
        Gate::define(Ability::ManagePaymentIntegration->value, [IntegrationSettingsPolicy::class, 'managePayment']);
        Gate::define(Ability::ManageSmsIntegration->value, [IntegrationSettingsPolicy::class, 'manageSms']);
        Gate::define(Ability::ManageEmailIntegration->value, [IntegrationSettingsPolicy::class, 'manageEmail']);
        Gate::define(Ability::ManageAiIntegration->value, [IntegrationSettingsPolicy::class, 'manageAi']);
        Gate::define(Ability::ManageFinanceSettings->value, [FinanceSettingsPolicy::class, 'manage']);
        Gate::define(Ability::EditFinanceSettingField->value, [FinanceSettingsPolicy::class, 'editField']);
        Gate::define(Ability::ManageAssessmentSettings->value, [AssessmentSettingsPolicy::class, 'manage']);
        Gate::define(Ability::ViewCommitteeRoster->value, [CommitteePolicy::class, 'view']);
        Gate::define(Ability::ManageCommitteeRoster->value, [CommitteePolicy::class, 'manage']);
        Gate::define(Ability::ManageCommitteeMember->value, [CommitteePolicy::class, 'manageMember']);
        Gate::define(Ability::ViewOwnCommitteeWork->value, [CommitteeAssignmentPolicy::class, 'viewOwnWork']);
        Gate::define(Ability::ReviewSecretaryDesk->value, [SecretaryDeskPolicy::class, 'review']);
        Gate::define(Ability::DecideSecretaryDesk->value, [SecretaryDeskPolicy::class, 'decide']);
        Gate::define(Ability::ViewSecretaryEvaluation->value, [SecretaryDeskPolicy::class, 'viewEvaluation']);
        Gate::define(Ability::ApproveSubstitution->value, [SecretaryDeskPolicy::class, 'approveSubstitution']);
        Gate::define(Ability::CloseCommitteeDiscussion->value, [SecretaryDeskPolicy::class, 'closeDiscussion']);
        Gate::define(Ability::ViewAwardResults->value, [AwardResultsPolicy::class, 'view']);
        Gate::define(Ability::RankAwardResult->value, [AwardResultsPolicy::class, 'rank']);
        Gate::define(Ability::ApproveRegionResults->value, [AwardResultsPolicy::class, 'approveRegion']);
        Gate::define(Ability::UnapproveRegionResults->value, [AwardResultsPolicy::class, 'unapproveRegion']);
        Gate::define(Ability::ApproveNationalResults->value, [AwardResultsPolicy::class, 'approveNational']);
        Gate::define(Ability::AnnounceAwardResults->value, [AwardResultsPolicy::class, 'announce']);

        Horizon::auth(static fn (Request $request): bool => $request->user() instanceof User && $request->user()->isSuperAdmin());

        Route::bind('managedUser', static fn (string $value): User => User::query()
            ->withoutSuperAdmins()
            ->whereKey((int) $value)
            ->firstOrFail());
    }
}
