<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Data\Integrations\ResolvedCredentials;
use App\Enums\Integrations\CredentialSource;
use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\SmtpEncryption;
use App\Services\Settings\IntegrationSettings;
use App\Support\ValueObjects\SecretValue;
use Illuminate\Contracts\Config\Repository;

final readonly class EnvironmentCredentials
{
    public function __construct(private Repository $config, private IntegrationSettings $settings) {}

    public function isEnabled(): bool
    {
        return $this->config->get('moashir.integrations.env_fallback') === true;
    }

    public function for(IntegrationKind $kind): ?ResolvedCredentials
    {
        return $this->isEnabled() ? $this->read($kind) : null;
    }

    public function read(IntegrationKind $kind): ?ResolvedCredentials
    {
        $credentials = match ($kind) {
            IntegrationKind::Payment => $this->payment(),
            IntegrationKind::Sms => $this->sms(),
            IntegrationKind::Email => $this->email(),
            IntegrationKind::Ai => $this->ai(),
        };

        return $credentials instanceof ResolvedCredentials && $this->isComplete($credentials) ? $credentials : null;
    }

    private function payment(): ?ResolvedCredentials
    {
        $mode = IntegrationMode::tryFrom($this->text('moashir.integrations.environment.payment.mode'));

        if (! $mode instanceof IntegrationMode) {
            return null;
        }

        return $this->resolved(IntegrationProvider::Moyasar, $mode, [
            'publishable_key' => $this->text('moashir.integrations.environment.payment.publishable_key'),
        ], [
            'secret_key' => $this->text('moashir.integrations.environment.payment.secret_key'),
            'webhook_secret' => $this->text('moashir.integrations.environment.payment.webhook_secret'),
        ]);
    }

    private function sms(): ?ResolvedCredentials
    {
        $provider = IntegrationProvider::tryFrom($this->text('moashir.integrations.environment.sms.provider'));
        $mode = $this->settings->mode(IntegrationKind::Sms);

        return match ($provider) {
            IntegrationProvider::Taqnyat => $this->resolved($provider, $mode, [
                'sender_name' => $this->text('moashir.integrations.environment.sms.taqnyat.sender_name'),
            ], ['bearer_token' => $this->text('moashir.integrations.environment.sms.taqnyat.bearer_token')]),
            IntegrationProvider::Msegat => $this->resolved($provider, $mode, [
                'username' => $this->text('moashir.integrations.environment.sms.msegat.username'),
                'sender_name' => $this->text('moashir.integrations.environment.sms.msegat.sender_name'),
            ], ['api_key' => $this->text('moashir.integrations.environment.sms.msegat.api_key')]),
            default => null,
        };
    }

    private function email(): ResolvedCredentials
    {
        $scheme = $this->text('mail.mailers.smtp.scheme');

        return $this->resolved(IntegrationProvider::Smtp, $this->settings->mode(IntegrationKind::Email), [
            'host' => $this->text('mail.mailers.smtp.host'),
            'port' => (int) $this->text('mail.mailers.smtp.port'),
            'encryption' => ($scheme === 'smtps' ? SmtpEncryption::Tls : SmtpEncryption::StartTls)->value,
            'username' => $this->text('mail.mailers.smtp.username'),
            'from_address' => $this->text('mail.from.address'),
            'from_name' => ['ar' => $this->text('mail.from.name'), 'en' => $this->text('mail.from.name')],
            'ehlo_domain' => $this->text('mail.mailers.smtp.local_domain'),
        ], ['password' => $this->text('mail.mailers.smtp.password')]);
    }

    private function ai(): ?ResolvedCredentials
    {
        $provider = IntegrationProvider::tryFrom($this->text('moashir.integrations.environment.ai.provider'));

        if (! in_array($provider, [IntegrationProvider::OpenAi, IntegrationProvider::Anthropic], true)) {
            return null;
        }

        return $this->resolved($provider, $this->settings->mode(IntegrationKind::Ai), [
            'model' => $this->text('moashir.integrations.environment.ai.model'),
        ], ['api_key' => $this->text('moashir.integrations.environment.ai.api_key')]);
    }

    /**
     * @param  array<string, mixed>  $public
     * @param  array<string, string>  $secrets
     */
    private function resolved(IntegrationProvider $provider, IntegrationMode $mode, array $public, array $secrets): ResolvedCredentials
    {
        $values = [];

        foreach (array_filter($secrets, static fn (string $value): bool => $value !== '') as $field => $value) {
            $values[$field] = new SecretValue($value);
        }

        return new ResolvedCredentials(
            $provider->kind(),
            $provider,
            $mode,
            CredentialSource::Environment,
            array_filter($public, static fn (mixed $value): bool => $value !== '' && $value !== 0),
            $values,
        );
    }

    private function isComplete(ResolvedCredentials $credentials): bool
    {
        $provider = $credentials->provider();

        foreach ($provider->requiredPublicFields() as $field) {
            if (! array_key_exists($field, $credentials->publicConfig)) {
                return false;
            }
        }

        return array_all($provider->requiredSecretFields(), fn (string $field): bool => $credentials->hasSecret($field));
    }

    private function text(string $key): string
    {
        $value = $this->config->get($key);

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
