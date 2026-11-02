<?php

declare(strict_types=1);

namespace App\Enums\Settings;

use App\Enums\Award\CommitteeEntityVisibility;
use App\Enums\Files\EvidenceFileType;
use App\Enums\Integrations\CardNetwork;
use App\Enums\Integrations\CheckoutPattern;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\PaymentLinkedPath;
use App\Enums\Integrations\PaymentMethod;
use App\Support\ValueObjects\LocalizedText;

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
    case SmsMode = 'sms.mode';
    case SmsTestRecipients = 'sms.test_recipients';
    case SmsTemplates = 'sms.templates';
    case EmailMode = 'email.mode';
    case EmailTestAddress = 'email.test_address';
    case AiMode = 'ai.mode';
    case AiPlacement = 'ai.placement';
    case AiNotice = 'ai.notice';
    case AccreditedPrice = 'assessment.accredited_price';
    case VatRate = 'assessment.vat_rate';
    case BagPrice = 'bag.price';
    case AbandonedCartAfterHours = 'abandoned_carts.after_hours';
    case FilesMaxSizeMb = 'files.max_size_mb';
    case AccreditedReviewSlaDays = 'assessment.accredited_review_sla_days';
    case EvidenceAllowedTypes = 'files.evidence_allowed_types';
    case EvidenceMaxFilesPerSub = 'files.evidence_max_files_per_sub';
    case AdvancedMinEntityAgeYears = 'assessment.advanced_min_entity_age_years';
    case AdvancedDisqualificationScore = 'assessment.advanced_disqualification_score';
    case AdvancedInitialReviewSlaDays = 'assessment.advanced_initial_review_sla_days';
    case CommitteeEntityDataVisibility = 'assessment.committee_entity_data_visibility';

    public function group(): SettingGroup
    {
        return SettingGroup::from(strstr($this->value, '.', true) ?: $this->value);
    }

    public function type(): SettingType
    {
        return match ($this) {
            self::PaymentMode, self::PaymentCheckoutPattern, self::SmsMode, self::EmailMode, self::EmailTestAddress,
            self::AiMode, self::AiPlacement, self::CommitteeEntityDataVisibility => SettingType::String,
            self::PaymentOrderValidityMinutes, self::AbandonedCartAfterHours, self::FilesMaxSizeMb,
            self::AccreditedReviewSlaDays, self::EvidenceMaxFilesPerSub, self::AdvancedMinEntityAgeYears,
            self::AdvancedInitialReviewSlaDays => SettingType::Integer,
            self::AccreditedPrice, self::VatRate, self::BagPrice, self::AdvancedDisqualificationScore => SettingType::Decimal,
            default => SettingType::Json,
        };
    }

    public function defaultValue(): mixed
    {
        return match ($this) {
            self::PaymentMode, self::AiMode => IntegrationMode::Test->value,
            self::SmsMode, self::EmailMode => IntegrationMode::Live->value,
            self::PaymentMethods => [PaymentMethod::CreditCard->value],
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
            self::FilesMaxSizeMb => 20,
            self::AccreditedReviewSlaDays => 14,
            self::EvidenceAllowedTypes => EvidenceFileType::values(),
            self::EvidenceMaxFilesPerSub => 10,
            self::AdvancedMinEntityAgeYears => 3,
            self::AdvancedDisqualificationScore => '60.00',
            self::AdvancedInitialReviewSlaDays => 14,
            self::CommitteeEntityDataVisibility => CommitteeEntityVisibility::NameOnly->value,
            self::PaymentApplePayDomainConfirmation, self::PaymentStcPayActivationConfirmation, self::EmailTestAddress,
            self::AccreditedPrice, self::BagPrice => null,
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
