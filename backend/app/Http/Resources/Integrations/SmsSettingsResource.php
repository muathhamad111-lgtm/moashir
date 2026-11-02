<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Data\Integrations\IntegrationSnapshot;
use App\Enums\Integrations\IntegrationProvider;
use App\Enums\Integrations\SmsTemplateKey;
use App\Enums\Settings\SettingKey;
use App\Models\Integrations\IntegrationCredential;
use App\Services\Integrations\MsegatTemplateGuard;
use App\Services\Settings\IntegrationSettings;
use App\Support\Integrations\CredentialPresenter;
use App\Support\Integrations\SmsTemplateMatcher;
use App\Support\StringList;
use App\Support\ValueObjects\LocalizedText;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read IntegrationSnapshot $resource
 */
final class SmsSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $snapshot = $this->resource;
        $presenter = new CredentialPresenter($snapshot);

        return [
            'active_provider' => $snapshot->activeProvider()?->value,
            'mode' => $snapshot->mode()->value,
            'source' => $snapshot->source->value,
            'test_recipients' => StringList::from($snapshot->setting(SettingKey::SmsTestRecipients)),
            'providers' => [
                'taqnyat' => [
                    'configured' => $presenter->isConfigured(IntegrationProvider::Taqnyat),
                    'bearer_token' => $presenter->secret(IntegrationProvider::Taqnyat, 'bearer_token'),
                    'sender_name' => $presenter->text(IntegrationProvider::Taqnyat, 'sender_name'),
                    'last_test' => $presenter->lastTest(IntegrationProvider::Taqnyat),
                ],
                'msegat' => [
                    'configured' => $presenter->isConfigured(IntegrationProvider::Msegat),
                    'username' => $presenter->text(IntegrationProvider::Msegat, 'username'),
                    'api_key' => $presenter->secret(IntegrationProvider::Msegat, 'api_key'),
                    'sender_name' => $presenter->text(IntegrationProvider::Msegat, 'sender_name'),
                    'template_restricted' => $presenter->value(IntegrationProvider::Msegat, 'template_restricted') === true,
                    'approved_templates' => $this->approvedTemplates(),
                    'approved_templates_loaded_at' => $snapshot->row(IntegrationProvider::Msegat)?->providerRef(MsegatTemplateGuard::TEMPLATES_LOADED_AT),
                    'last_test' => $presenter->lastTest(IntegrationProvider::Msegat),
                ],
            ],
            'templates' => array_map($this->template(...), SmsTemplateKey::cases()),
            'checklist' => [
                ['key' => 'sender_registration', 'confirmable' => false, 'confirmed' => null],
                ['key' => 'api_sending_and_ip', 'confirmable' => false, 'confirmed' => null],
                ['key' => 'msegat_templates', 'confirmable' => false, 'confirmed' => null],
                ['key' => 'balance', 'confirmable' => false, 'confirmed' => null],
            ],
        ];
    }

    /**
     * @return list<array{id: string, title: string, body: string}>
     */
    private function approvedTemplates(): array
    {
        $templates = $this->resource->row(IntegrationProvider::Msegat)?->providerRef(MsegatTemplateGuard::APPROVED_TEMPLATES);
        $list = [];

        foreach (is_array($templates) ? $templates : [] as $template) {
            if (is_array($template)) {
                $list[] = [
                    'id' => is_scalar($template['id'] ?? null) ? (string) $template['id'] : '',
                    'title' => is_string($template['title'] ?? null) ? $template['title'] : '',
                    'body' => is_string($template['body'] ?? null) ? $template['body'] : '',
                ];
            }
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    private function template(SmsTemplateKey $key): array
    {
        $stored = $this->resource->setting(SettingKey::SmsTemplates);
        $body = is_array($stored) && is_array($stored[$key->value] ?? null) ? LocalizedText::fromArray($stored[$key->value]) : IntegrationSettings::defaultSmsTemplate($key);

        return [
            'key' => $key->value,
            'label' => LocalizedText::fromTranslation('integrations.sms_templates.'.$key->value.'.label')->toArray(),
            'body' => $body->toArray(),
            'default_body' => IntegrationSettings::defaultSmsTemplate($key)->toArray(),
            'placeholders' => $key->placeholders(),
            'required_placeholders' => $key->requiredPlaceholders(),
            'single_segment' => $key->singleSegmentOnly(),
            'matches_msegat_template' => $this->matchesMsegat($body),
        ];
    }

    /**
     * @return array{ar: bool, en: bool}|null
     */
    private function matchesMsegat(LocalizedText $body): ?array
    {
        $msegat = $this->resource->row(IntegrationProvider::Msegat);

        if (! $msegat instanceof IntegrationCredential || $msegat->providerRef(MsegatTemplateGuard::TEMPLATES_LOADED_AT) === null) {
            return null;
        }

        $approved = MsegatTemplateGuard::approvedBodies($msegat);

        return ['ar' => SmsTemplateMatcher::matchesAny($body->ar, $approved), 'en' => SmsTemplateMatcher::matchesAny($body->en, $approved)];
    }
}
