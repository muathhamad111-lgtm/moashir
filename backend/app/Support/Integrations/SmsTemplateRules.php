<?php

declare(strict_types=1);

namespace App\Support\Integrations;

use App\Enums\Integrations\SmsTemplateKey;
use App\Services\Integrations\SmsTemplateRenderer;
use App\Support\ScalarValue;
use Illuminate\Validation\Validator;

final class SmsTemplateRules
{
    public const int MAX_LENGTH = 480;

    public const int SINGLE_SEGMENT_UNICODE = 70;

    public const int SINGLE_SEGMENT_LATIN = 160;

    public static function validate(Validator $validator): void
    {
        $templates = $validator->getData()['templates'] ?? [];

        foreach (is_array($templates) ? $templates : [] as $index => $template) {
            $key = SmsTemplateKey::tryFrom(ScalarValue::string(is_array($template) ? ($template['key'] ?? null) : null));
            $bodies = is_array($template) && is_array($template['body'] ?? null) ? $template['body'] : [];

            if (! $key instanceof SmsTemplateKey) {
                continue;
            }

            foreach (['ar', 'en'] as $locale) {
                $error = self::errorFor($key, ScalarValue::string($bodies[$locale] ?? null), $locale);

                if ($error !== null) {
                    $validator->errors()->add('templates.'.$index.'.body.'.$locale, __('validation.custom.sms_template.'.$error));
                }
            }
        }
    }

    public static function errorFor(SmsTemplateKey $key, string $body, string $locale): ?string
    {
        preg_match_all('/\{([a-z_]+)\}/', $body, $matches);
        $used = $matches[1];

        if (array_diff($used, $key->placeholders()) !== []) {
            return 'unknown_placeholder';
        }

        if (array_diff($key->requiredPlaceholders(), $used) !== []) {
            return 'missing_placeholder';
        }

        if ($key->singleSegmentOnly() && ! self::fitsOneSegment(SmsTemplateRenderer::fill($body, $key->longestSample($locale)))) {
            return 'too_long';
        }

        return null;
    }

    public static function fitsOneSegment(string $text): bool
    {
        $isLatin = preg_match('/^[\x20-\x7E\r\n]*$/', $text) === 1;

        return mb_strlen($text) <= ($isLatin ? self::SINGLE_SEGMENT_LATIN : self::SINGLE_SEGMENT_UNICODE);
    }

    /**
     * @param  array<array-key, mixed>  $templates
     * @return array<string, array{ar: string, en: string}>
     */
    public static function keyed(array $templates): array
    {
        $keyed = [];

        foreach ($templates as $template) {
            if (! is_array($template) || ! is_array($template['body'] ?? null)) {
                continue;
            }

            $keyed[ScalarValue::string($template['key'] ?? null)] = [
                'ar' => ScalarValue::string($template['body']['ar'] ?? null),
                'en' => ScalarValue::string($template['body']['en'] ?? null),
            ];
        }

        return $keyed;
    }
}
