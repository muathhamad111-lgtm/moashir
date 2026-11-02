<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use App\Enums\Integrations\SmsTemplateKey;
use App\Services\Settings\IntegrationSettings;

final readonly class SmsTemplateRenderer
{
    public function __construct(private IntegrationSettings $settings) {}

    /**
     * @param  array<string, string>  $values
     */
    public function render(SmsTemplateKey $key, string $locale, array $values): string
    {
        return self::fill($this->settings->smsTemplate($key)->in($locale), $values);
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function fill(string $body, array $values): string
    {
        $replacements = [];

        foreach ($values as $name => $value) {
            $replacements['{'.$name.'}'] = $value;
        }

        return strtr($body, $replacements);
    }
}
