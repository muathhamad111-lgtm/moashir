<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Contracts\Integrations\SmsProviderDriver;
use App\Contracts\SmsGateway;
use App\Data\Integrations\ResolvedCredentials;
use App\Data\Integrations\SmsMessage;
use App\Data\Integrations\SmsSendResult;
use App\Enums\Integrations\IntegrationKind;
use App\Exceptions\Integrations\ProviderCallFailed;
use App\Exceptions\Integrations\SmsDeliveryFailed;
use App\Services\Settings\IntegrationSettings;
use Psr\Log\LoggerInterface;

final readonly class SettingsSmsGateway implements SmsGateway
{
    public function __construct(
        private IntegrationCredentialsResolver $resolver,
        private ProviderAdapterRegistry $adapters,
        private IntegrationSettings $settings,
        private LoggerInterface $logger,
    ) {}

    public function send(SmsMessage $message): SmsSendResult
    {
        $credentials = $this->resolver->resolve(IntegrationKind::Sms);

        if (! $credentials->isConfigured()) {
            throw SmsDeliveryFailed::notConfigured();
        }

        return $this->sendWith($credentials, $message);
    }

    public function sendWith(ResolvedCredentials $credentials, SmsMessage $message): SmsSendResult
    {
        if ($credentials->mode->isTest() && ! in_array($message->recipient->value, $this->settings->smsTestRecipients(), true)) {
            $this->logger->info('integration.sms_suppressed', ['provider' => $credentials->provider?->value, 'purpose' => $message->purpose->value]);

            return SmsSendResult::suppressedInTestMode($credentials->provider);
        }

        try {
            return $this->adapters->get(SmsProviderDriver::class, $credentials->provider())->send($credentials, $message);
        } catch (ProviderCallFailed $failure) {
            throw SmsDeliveryFailed::from($failure);
        }
    }
}
