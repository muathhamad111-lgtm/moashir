<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

use App\Enums\Audit\AuditEvent;
use App\Enums\Identity\PermissionKey;

enum IntegrationKind: string
{
    case Payment = 'payment';
    case Sms = 'sms';
    case Email = 'email';
    case Ai = 'ai';

    /**
     * @return list<IntegrationProvider>
     */
    public function providers(): array
    {
        return array_values(array_filter(
            IntegrationProvider::cases(),
            fn (IntegrationProvider $provider): bool => $provider->kind() === $this,
        ));
    }

    public function permission(): PermissionKey
    {
        return match ($this) {
            self::Payment => PermissionKey::SettingsPaymentManage,
            self::Sms => PermissionKey::SettingsSmsManage,
            self::Email => PermissionKey::SettingsEmailManage,
            self::Ai => PermissionKey::AiProviderManage,
        };
    }

    public function updatedAuditEvent(): AuditEvent
    {
        return match ($this) {
            self::Payment => AuditEvent::PaymentSettingsUpdated,
            self::Sms => AuditEvent::SmsSettingsUpdated,
            self::Email => AuditEvent::EmailSettingsUpdated,
            self::Ai => AuditEvent::AiSettingsUpdated,
        };
    }

    public function testedAuditEvent(): AuditEvent
    {
        return match ($this) {
            self::Payment => AuditEvent::PaymentConnectionTested,
            self::Sms => AuditEvent::SmsConnectionTested,
            self::Email => AuditEvent::EmailConnectionTested,
            self::Ai => AuditEvent::AiConnectionTested,
        };
    }

    public function hasModePerCredential(): bool
    {
        return $this === self::Payment;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
