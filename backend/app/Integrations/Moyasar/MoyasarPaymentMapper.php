<?php

declare(strict_types=1);

namespace App\Integrations\Moyasar;

use App\Data\Integrations\ProviderPayment;
use App\Support\ScalarValue;
use App\Support\ValueObjects\Money;

final class MoyasarPaymentMapper
{
    private const int MESSAGE_LIMIT = 255;

    /**
     * @param  array<array-key, mixed>  $payment
     */
    public static function fromPayload(array $payment): ProviderPayment
    {
        $source = is_array($payment['source'] ?? null) ? $payment['source'] : [];

        return new ProviderPayment(
            ScalarValue::string($payment['id'] ?? null),
            ScalarValue::string($payment['status'] ?? null),
            MoyasarAmount::fromHalalas($payment['amount'] ?? null, ScalarValue::string($payment['currency'] ?? null, Money::SAUDI_RIYAL)),
            self::optional($source['type'] ?? null),
            self::optional($source['company'] ?? null),
            self::lastFour($source['number'] ?? null),
            self::optional($source['response_code'] ?? null),
            self::message($source['message'] ?? null),
            self::metadata($payment['metadata'] ?? null),
        );
    }

    private static function optional(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function lastFour(mixed $number): ?string
    {
        if (! is_string($number)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $number) ?? '';

        return strlen($digits) >= 4 ? substr($digits, -4) : null;
    }

    private static function message(mixed $message): ?string
    {
        return is_string($message) ? mb_substr(strip_tags($message), 0, self::MESSAGE_LIMIT) : null;
    }

    /**
     * @return array<string, string>
     */
    private static function metadata(mixed $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        $values = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $values[$key] = (string) $value;
            }
        }

        return $values;
    }
}
