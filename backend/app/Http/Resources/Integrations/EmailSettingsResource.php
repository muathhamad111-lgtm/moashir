<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Data\Integrations\IntegrationSnapshot;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\SmtpEncryption;
use App\Enums\Settings\SettingKey;
use App\Support\Integrations\CredentialPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read IntegrationSnapshot $resource
 */
final class EmailSettingsResource extends JsonResource
{
    public const int DEFAULT_PORT = 587;

    public const int DEFAULT_TIMEOUT_SECONDS = 15;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->resource;
        $presenter = new CredentialPresenter($snapshot);
        $smtp = IntegrationProvider::Smtp;
        $fromName = $presenter->value($smtp, 'from_name');
        $testAddress = $snapshot->setting(SettingKey::EmailTestAddress);

        return [
            'active' => $snapshot->isActive(),
            'mode' => $snapshot->mode()->value,
            'source' => $snapshot->source->value,
            'configured' => $presenter->isConfigured($smtp),
            'host' => $presenter->text($smtp, 'host'),
            'port' => (int) ($presenter->text($smtp, 'port') ?? self::DEFAULT_PORT),
            'encryption' => $presenter->text($smtp, 'encryption') ?? SmtpEncryption::StartTls->value,
            'username' => $presenter->text($smtp, 'username'),
            'password' => $presenter->secret($smtp, 'password'),
            'from_address' => $presenter->text($smtp, 'from_address'),
            'from_name' => is_array($fromName) ? $fromName : null,
            'reply_to' => $presenter->text($smtp, 'reply_to'),
            'ehlo_domain' => $presenter->text($smtp, 'ehlo_domain'),
            'timeout_seconds' => (int) ($presenter->text($smtp, 'timeout_seconds') ?? self::DEFAULT_TIMEOUT_SECONDS),
            'test_address' => is_string($testAddress) ? $testAddress : null,
            'last_test' => $presenter->lastTest($smtp),
            'checklist' => [
                ['key' => 'mailbox', 'confirmable' => false, 'confirmed' => null],
                ['key' => 'spf', 'confirmable' => false, 'confirmed' => null],
                ['key' => 'dkim', 'confirmable' => false, 'confirmed' => null],
                ['key' => 'dmarc', 'confirmable' => false, 'confirmed' => null],
            ],
        ];
    }
}
