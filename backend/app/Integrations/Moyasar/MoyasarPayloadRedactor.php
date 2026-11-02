<?php

declare(strict_types=1);

namespace App\Integrations\Moyasar;

final class MoyasarPayloadRedactor
{
    private const array REMOVED_SOURCE_FIELDS = ['name', 'mobile'];

    /**
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public static function redact(array $payload): array
    {
        unset($payload['secret_token']);

        if (! is_array($payload['data'] ?? null)) {
            return $payload;
        }

        $data = $payload['data'];
        unset($data['ip']);

        if (is_array($data['source'] ?? null)) {
            $data['source'] = self::redactSource($data['source']);
        }

        $payload['data'] = $data;

        return $payload;
    }

    /**
     * @param  array<array-key, mixed>  $source
     * @return array<array-key, mixed>
     */
    private static function redactSource(array $source): array
    {
        foreach (self::REMOVED_SOURCE_FIELDS as $field) {
            unset($source[$field]);
        }

        if (is_string($source['number'] ?? null)) {
            $digits = preg_replace('/\D/', '', $source['number']) ?? '';
            $source['number'] = strlen($digits) >= 4 ? substr($digits, -4) : null;
        }

        return $source;
    }
}
