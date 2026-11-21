<?php

declare(strict_types=1);

namespace App\Enums\Settings;

use App\Enums\Award\CommitteeEntityVisibility;
use App\Enums\Certificates\CertificateIssuanceMode;
use App\Enums\Certificates\CertificateLevel;
use App\Enums\Files\EvidenceFileType;
use App\Enums\Finance\PaymentChannel;
use App\Enums\Identity\OtpChannel;
use App\Enums\Identity\RoleKey;
use App\Enums\Integrations\CardNetwork;
use App\Enums\Integrations\CheckoutPattern;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\PaymentLinkedPath;
use App\Enums\Integrations\PaymentMethod;
use App\Enums\Periods\CertificateValidityRule;
use App\Enums\Periods\PeriodCadence;
use App\Enums\Periods\PeriodicAudience;
use App\Support\ValueObjects\GapThresholds;
use App\Support\ValueObjects\LocalizedText;
use App\Support\ValueObjects\MaturityBounds;
use App\Support\ValueObjects\PathEntityTexts;

enum SettingKey: string
{
    case PaymentMode = 'payment.mode';
    case PaymentMethods = 'payment.methods';
    case PaymentSupportedNetworks = 'payment.supported_networks';
    case PaymentApplePayLabel = 'payment.apple_pay_label';
    case PaymentLinkedPaths = 'payment.linked_paths';
    case PaymentCheckoutPattern = 'payment.checkout_pattern';
    case PaymentApplePayDomainConfirmation = 'payment.apple_pay_domain_confirmation';
    case PaymentStcPayActivationConfirmation = 'payment.stcpay_activation_confirmation';
    case PaymentOrderValidityMinutes = 'payment.order_validity_minutes';
    case PaymentChannels = 'payment.channels';
    case SmsMode = 'sms.mode';
    case SmsTestRecipients = 'sms.test_recipients';
    case SmsTemplates = 'sms.templates';
    case EmailMode = 'email.mode';
    case EmailTestAddress = 'email.test_address';
    case AiMode = 'ai.mode';
    case AiPlacement = 'ai.placement';
    case AiNotice = 'ai.notice';
    case VatRate = 'assessment.vat_rate';
    case AbandonedCartAfterHours = 'abandoned_carts.after_hours';
    case FilesMaxSizeMb = 'files.max_size_mb';
    case AccreditedReviewSlaDays = 'assessment.accredited_review_sla_days';
    case AccreditedUploadMaxFileMb = 'assessment.accredited_upload_max_file_mb';
    case AccreditedEvidenceAllowedTypes = 'assessment.accredited_evidence_allowed_types';
    case AccreditedEvidenceMaxFilesPerSub = 'assessment.accredited_evidence_max_files_per_sub';
    case AccreditedEntityTexts = 'assessment.accredited_entity_texts';
    case AdvancedUploadMaxFileMb = 'assessment.advanced_upload_max_file_mb';
    case AdvancedEvidenceAllowedTypes = 'assessment.advanced_evidence_allowed_types';
    case AdvancedEvidenceMaxFilesPerSub = 'assessment.advanced_evidence_max_files_per_sub';
    case AdvancedEntityTexts = 'assessment.advanced_entity_texts';
    case AdvancedMinEntityAgeYears = 'assessment.advanced_min_entity_age_years';
    case AdvancedDisqualificationScore = 'assessment.advanced_disqualification_score';
    case AdvancedInitialReviewSlaDays = 'assessment.advanced_initial_review_sla_days';
    case CommitteeEntityDataVisibility = 'assessment.committee_entity_data_visibility';
    case AssessmentScaleKind = 'assessment.scale_kind';
    case AssignReasonRequired = 'assessment.assign_reason_required';
    case ReassignReasonRequired = 'assessment.reassign_reason_required';
    case CertificateLevelThresholds = 'certificates.level_thresholds';
    case AccreditedCertificateIssuanceMode = 'certificates.accredited_issuance_mode';
    case ContactRetentionMonths = 'contact.retention_months';
    case EntitySignInCodeChannels = 'auth.entity_sign_in_code_channels';
    case GeneralEmailNotifications = 'auth.general_email_notifications';
    case EntityRegistrationReview = 'auth.entity_registration_review';
    case TwoFactorEnabled = 'auth.two_factor_enabled';
    case TwoFactorRoles = 'auth.two_factor_roles';
    case NationalIdEnabled = 'auth.national_id_enabled';
    case NationalIdRoles = 'auth.national_id_roles';
    case InsightsGapThresholds = 'insights.gap_thresholds';
    case InsightsMaturityBands = 'insights.maturity_bands';
    case VatRegistrationNumber = 'finance.vat_registration_number';
    case PeriodicCadence = 'periodic.cadence';
    case PeriodicNoticeDaysBefore = 'periodic.notice_days_before';
    case PeriodicReminderDaysBeforeEnd = 'periodic.reminder_days_before_end';
    case PeriodicAudience = 'periodic.audience';
    case PeriodicCertificateValidity = 'periodic.certificate_validity';
    case PeriodicPaidDraftReminderDays = 'periodic.paid_draft_reminder_days';
    case PeriodicRolloutFrom = 'periodic.rollout_from';
    case SelfPathDescription = 'paths.self.description';
    case AccreditedPathDescription = 'paths.accredited.description';
    case AdvancedPathDescription = 'paths.advanced.description';

    public function group(): SettingGroup
    {
        return SettingGroup::from(strstr($this->value, '.', true) ?: $this->value);
    }

    public function type(): SettingType
    {
        return match ($this) {
            self::PaymentMode, self::PaymentCheckoutPattern, self::SmsMode, self::EmailMode, self::EmailTestAddress,
            self::AiMode, self::AiPlacement, self::CommitteeEntityDataVisibility, self::AssessmentScaleKind, self::VatRegistrationNumber,
            self::PeriodicCadence, self::PeriodicAudience, self::PeriodicCertificateValidity, self::PeriodicRolloutFrom,
            self::AccreditedCertificateIssuanceMode => SettingType::String,
            self::PaymentOrderValidityMinutes, self::AbandonedCartAfterHours, self::FilesMaxSizeMb,
            self::AccreditedReviewSlaDays, self::AdvancedMinEntityAgeYears, self::AdvancedInitialReviewSlaDays,
            self::AccreditedUploadMaxFileMb, self::AccreditedEvidenceMaxFilesPerSub, self::AdvancedUploadMaxFileMb,
            self::AdvancedEvidenceMaxFilesPerSub, self::ContactRetentionMonths, self::PeriodicNoticeDaysBefore,
            self::PeriodicPaidDraftReminderDays => SettingType::Integer,
            self::VatRate, self::AdvancedDisqualificationScore => SettingType::Decimal,
            self::GeneralEmailNotifications, self::EntityRegistrationReview,
            self::TwoFactorEnabled, self::NationalIdEnabled, self::AssignReasonRequired, self::ReassignReasonRequired => SettingType::Boolean,
            default => SettingType::Json,
        };
    }

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::PaymentMode, self::AiMode => IntegrationMode::Test->value,
            self::SmsMode, self::EmailMode => IntegrationMode::Live->value,
            self::PaymentMethods => [PaymentMethod::CreditCard->value],
            self::PaymentChannels => [PaymentChannel::Electronic->value],
            self::PaymentSupportedNetworks => [CardNetwork::Mada->value, CardNetwork::Visa->value, CardNetwork::Mastercard->value],
            self::PaymentApplePayLabel => LocalizedText::fromTranslation('integrations.defaults.apple_pay_label')->toArray(),
            self::PaymentLinkedPaths => PaymentLinkedPath::values(),
            self::PaymentCheckoutPattern => CheckoutPattern::Intent->value,
            self::PaymentOrderValidityMinutes => 30,
            self::SmsTestRecipients, self::SmsTemplates => [],
            self::AiPlacement => 'public_only',
            self::AiNotice => LocalizedText::fromTranslation('integrations.defaults.ai_notice')->toArray(),
            self::VatRate => '15.00',
            self::AbandonedCartAfterHours => 24,
            self::FilesMaxSizeMb, self::AccreditedUploadMaxFileMb, self::AdvancedUploadMaxFileMb => 20,
            self::AccreditedReviewSlaDays => 14,
            self::AccreditedEvidenceAllowedTypes, self::AdvancedEvidenceAllowedTypes => EvidenceFileType::values(),
            self::AccreditedEvidenceMaxFilesPerSub, self::AdvancedEvidenceMaxFilesPerSub => 10,
            self::AccreditedEntityTexts, self::AdvancedEntityTexts => PathEntityTexts::empty()->toArray(),
            self::AdvancedMinEntityAgeYears => 3,
            self::AdvancedDisqualificationScore => '60.00',
            self::AdvancedInitialReviewSlaDays => 14,
            self::CommitteeEntityDataVisibility => CommitteeEntityVisibility::NameOnly->value,
            self::CertificateLevelThresholds => CertificateLevel::DEFAULT_THRESHOLDS,
            self::AccreditedCertificateIssuanceMode => CertificateIssuanceMode::Automatic->value,
            self::EntitySignInCodeChannels => [OtpChannel::Email->value],
            self::GeneralEmailNotifications, self::EntityRegistrationReview, self::TwoFactorEnabled => true,
            self::TwoFactorRoles => RoleKey::staffValues(),
            self::NationalIdEnabled, self::AssignReasonRequired => false,
            self::ReassignReasonRequired => true,
            self::NationalIdRoles => [],
            self::ContactRetentionMonths => 24,
            self::InsightsGapThresholds => GapThresholds::DEFAULTS,
            self::InsightsMaturityBands => MaturityBounds::DEFAULTS,
            self::PeriodicCadence => PeriodCadence::Quarterly->value,
            self::PeriodicNoticeDaysBefore => PeriodCadence::Quarterly->defaultNoticeDays(),
            self::PeriodicReminderDaysBeforeEnd => PeriodCadence::Quarterly->defaultReminderDays(),
            self::PeriodicAudience => PeriodicAudience::ActiveVerifiedEntities->value,
            self::PeriodicCertificateValidity => CertificateValidityRule::NextPeriodEnd->value,
            self::PeriodicPaidDraftReminderDays => 7,
            self::SelfPathDescription => LocalizedText::fromTranslation('assessments.path_descriptions.self')->toArray(),
            self::AccreditedPathDescription => LocalizedText::fromTranslation('assessments.path_descriptions.accredited')->toArray(),
            self::AdvancedPathDescription => LocalizedText::fromTranslation('assessments.path_descriptions.advanced')->toArray(),
            self::PaymentApplePayDomainConfirmation, self::PaymentStcPayActivationConfirmation, self::EmailTestAddress,
            self::VatRegistrationNumber, self::PeriodicRolloutFrom, self::AssessmentScaleKind => null,
        };
    }

    public static function modeOf(IntegrationKind $kind): self
    {
        return match ($kind) {
            IntegrationKind::Payment => self::PaymentMode,
            IntegrationKind::Sms => self::SmsMode,
            IntegrationKind::Email => self::EmailMode,
            IntegrationKind::Ai => self::AiMode,
        };
    }
}
