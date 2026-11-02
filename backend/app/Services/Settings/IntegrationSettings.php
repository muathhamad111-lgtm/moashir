<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Enums\Integrations\IntegrationKind;
use App\Enums\Integrations\IntegrationMode;
use App\Enums\Integrations\SmsTemplateKey;
use App\Enums\Settings\SettingKey;
use App\Support\ScalarValue;
use App\Support\StringList;
use App\Support\ValueObjects\LocalizedText;

final readonly class IntegrationSettings
{
    public function __construct(private SettingsStore $store) {}

    public function mode(IntegrationKind $kind): IntegrationMode
    {
        $key = SettingKey::modeOf($kind);
        $value = $this->store->get($key);

        return IntegrationMode::tryFrom(is_string($value) ? $value : '') ?? IntegrationMode::from(ScalarValue::string($key->defaultValue()));
    }

    /**
     * @return list<string>
     */
    public function smsTestRecipients(): array
    {
        return StringList::from($this->store->get(SettingKey::SmsTestRecipients));
    }

    public function emailTestAddress(): ?string
    {
        $address = $this->store->get(SettingKey::EmailTestAddress);

        return is_string($address) && $address !== '' ? $address : null;
    }

    public function smsTemplate(SmsTemplateKey $key): LocalizedText
    {
        $stored = $this->store->get(SettingKey::SmsTemplates);
        $template = is_array($stored) && is_array($stored[$key->value] ?? null) ? $stored[$key->value] : null;

        return $template === null ? self::defaultSmsTemplate($key) : LocalizedText::fromArray($template);
    }

    public static function defaultSmsTemplate(SmsTemplateKey $key): LocalizedText
    {
        return LocalizedText::fromTranslation('integrations.sms_templates.'.$key->value.'.default_body');
    }
}
