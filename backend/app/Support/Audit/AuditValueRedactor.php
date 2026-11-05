<?php

declare(strict_types=1);

namespace App\Support\Audit;

use App\Support\Security\SensitiveUrl;

final class AuditValueRedactor
{
    private const string SECRET_KEY = '/(password|secret|token|api_?key|code_hash|two_factor|recovery|signature|bearer|credential|phone_index|identifier_hash)/i';

    /**
     * @param  array<array-key, mixed>|null  $values
     * @return array<array-key, mixed>|null
     */
    public static function redact(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $redacted = [];

        foreach ($values as $key => $value) {
            $redacted[$key] = match (true) {
                is_string($key) && preg_match(self::SECRET_KEY, $key) === 1 => SensitiveUrl::REDACTED,
                is_array($value) => self::redact($value),
                is_string($value) => SensitiveUrl::redact($value),
                default => $value,
            };
        }

        return $redacted;
    }
}
