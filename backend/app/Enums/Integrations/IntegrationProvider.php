<?php

declare(strict_types=1);

namespace App\Enums\Integrations;

enum IntegrationProvider: string
{
    case Moyasar = 'moyasar';
    case Taqnyat = 'taqnyat';
    case Msegat = 'msegat';
    case Smtp = 'smtp';
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';

    private const int LAST4_MINIMUM_LENGTH = 20;

    public function kind(): IntegrationKind
    {
        return match ($this) {
            self::Moyasar => IntegrationKind::Payment,
            self::Taqnyat, self::Msegat => IntegrationKind::Sms,
            self::Smtp => IntegrationKind::Email,
            self::OpenAi, self::Anthropic => IntegrationKind::Ai,
        };
    }

    /**
     * @return list<string>
     */
    public function requiredPublicFields(): array
    {
        return match ($this) {
            self::Moyasar => ['publishable_key'],
            self::Taqnyat => ['sender_name'],
            self::Msegat => ['username', 'sender_name'],
            self::Smtp => ['host', 'username', 'from_address', 'from_name'],
            self::OpenAi, self::Anthropic => ['model'],
        };
    }

    /**
     * @return list<string>
     */
    public function secretFields(): array
    {
        return match ($this) {
            self::Moyasar => ['secret_key', 'webhook_secret'],
            self::Taqnyat => ['bearer_token'],
            self::Msegat, self::OpenAi, self::Anthropic => ['api_key'],
            self::Smtp => ['password'],
        };
    }

    /**
     * @return list<string>
     */
    public function requiredSecretFields(): array
    {
        return match ($this) {
            self::Moyasar => ['secret_key'],
            default => $this->secretFields(),
        };
    }

    public function affectsConnectionTest(string $field): bool
    {
        return $this !== self::Moyasar || $field !== 'webhook_secret';
    }

    public function revealsLast4(string $secretField, string $value): bool
    {
        $isApiKey = in_array($secretField, ['secret_key', 'bearer_token', 'api_key'], true);

        return $isApiKey && mb_strlen($value) >= self::LAST4_MINIMUM_LENGTH;
    }

    /**
     * @return list<string>
     */
    public static function valuesOf(IntegrationKind $kind): array
    {
        return array_map(static fn (self $provider): string => $provider->value, $kind->providers());
    }
}
