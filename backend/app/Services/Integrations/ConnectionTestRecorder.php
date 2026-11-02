<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Audit\AuditEntry;
use App\Data\Integrations\ConnectionTestResult;
use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Notifications\NotificationCode;
use App\Models\Identity\User;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Audit\AuditRecorder;
use Illuminate\Support\Facades\Date;

final readonly class ConnectionTestRecorder
{
    public function __construct(private AuditRecorder $audit, private IntegrationNotifier $notifier) {}

    public function record(ResolvedCredentials $credentials, ConnectionTestResult $result, User $actor): void
    {
        $row = $credentials->credentialId === null ? null : IntegrationCredential::query()->find($credentials->credentialId);

        $row?->forceFill([
            'last_test_at' => Date::now(),
            'last_test_ok' => $result->ok(),
            'last_test_error_kind' => $result->errorKind,
            'last_test_latency_ms' => $result->latencyMs,
            'last_test_by_user_id' => $actor->id,
        ])->save();

        $this->audit->record(new AuditEntry($credentials->kind->testedAuditEvent(), $row ?? $actor, [], [
            'provider' => $result->provider->value,
            'mode' => $result->mode->value,
            'source' => $credentials->source->value,
            'ok' => $result->ok(),
            'error_kind' => $result->errorKind?->value,
            'latency_ms' => $result->latencyMs,
        ]));

        $code = $this->notificationFor($credentials->kind, $result->ok());

        if ($code instanceof NotificationCode) {
            $this->notifier->notify($code, $actor->id, ['provider' => $result->provider->value, 'error' => $result->errorKind->value ?? '']);
        }
    }

    private function notificationFor(IntegrationKind $kind, bool $ok): ?NotificationCode
    {
        return match (true) {
            $kind === IntegrationKind::Sms => $ok ? NotificationCode::SmsGatewayTestPassed : NotificationCode::SmsSendFailed,
            $ok => null,
            $kind === IntegrationKind::Payment => NotificationCode::PaymentGatewayTestFailed,
            $kind === IntegrationKind::Ai => NotificationCode::AiProviderConnectionFailed,
            default => null,
        };
    }
}
