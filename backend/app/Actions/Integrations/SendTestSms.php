<?php

declare(strict_types=1);

namespace App\Actions\Integrations;

use App\Data\Audit\AuditEntry;
use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsMessage;
use App\Data\Integrations\SmsSendResult;
use App\Enums\Audit\AuditEvent;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\SmsPurpose;
use App\Exceptions\Integrations\IntegrationRejected;
use App\Exceptions\Integrations\SmsDeliveryFailed;
use App\Models\Identity\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Integrations\IntegrationProbe;
use App\Services\Integrations\IntegrationRows;
use App\Services\Integrations\SettingsSmsGateway;
use App\Services\Settings\IntegrationSettings;
use App\Support\ValueObjects\PhoneNumber;
use Illuminate\Support\Facades\Date;

final readonly class SendTestSms
{
    public function __construct(
        private IntegrationProbe $probe,
        private IntegrationRows $rows,
        private IntegrationSettings $settings,
        private SettingsSmsGateway $gateway,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $actor, PhoneNumber $recipient, ?IntegrationProvider $provider): SmsSendResult
    {
        $credentials = $this->probe->configured($provider ?? $this->activeProvider());

        if ($credentials->mode->isTest() && ! in_array($recipient->value, $this->settings->smsTestRecipients(), true)) {
            throw IntegrationRejected::recipientNotAllowedInTestMode();
        }

        try {
            $result = $this->gateway->sendWith($credentials, new SmsMessage($recipient, $this->text(), SmsPurpose::ConnectionTest));
        } catch (SmsDeliveryFailed $failure) {
            $this->record($actor, $credentials, $recipient, $failure->kind->value);

            throw IntegrationRejected::providerError($failure->kind);
        }

        $this->record($actor, $credentials, $recipient, null);

        return $result;
    }

    private function activeProvider(): IntegrationProvider
    {
        return $this->rows->active(IntegrationKind::Sms)->provider ?? throw IntegrationRejected::notConfigured(IntegrationKind::Sms);
    }

    private function text(): string
    {
        return __('integrations.sms_test.body', ['time' => Date::now()->timezone('Asia/Riyadh')->format('Y-m-d H:i:s')]);
    }

    private function record(User $actor, ResolvedCredentials $credentials, PhoneNumber $recipient, ?string $errorKind): void
    {
        $this->audit->record(new AuditEntry(AuditEvent::SmsTestSent, $actor, [], [
            'provider' => $credentials->provider?->value,
            'mode' => $credentials->mode->value,
            'recipient' => $recipient->masked(),
            'ok' => $errorKind === null,
            'error_kind' => $errorKind,
        ]));
    }
}
