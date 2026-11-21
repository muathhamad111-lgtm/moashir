<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class Iban
{
    public const string SAUDI_PATTERN = '/^SA[0-9]{2}[0-9]{2}[A-Z0-9]{18}$/';

    private const int CHECKSUM_MODULUS = 97;

    private const int VALID_REMAINDER = 1;

    private const int COUNTRY_AND_CHECK_LENGTH = 4;

    private const int LETTER_OFFSET = 55;

    public string $value;

    public function __construct(string $input)
    {
        $normalized = self::normalize($input);

        if (! self::hasSaudiFormat($normalized) || ! self::hasValidChecksum($normalized)) {
            throw new InvalidArgumentException('A Saudi IBAN has SA, two check digits, a two-digit bank code and 18 letters or digits, and a valid ISO 13616 checksum.');
        }

        $this->value = $normalized;
    }

    public static function normalize(string $input): string
    {
        return mb_strtoupper((string) preg_replace('/\s+/u', '', $input));
    }

    public static function hasSaudiFormat(string $normalized): bool
    {
        return preg_match(self::SAUDI_PATTERN, $normalized) === 1;
    }

    public static function hasValidChecksum(string $normalized): bool
    {
        $rearranged = substr($normalized, self::COUNTRY_AND_CHECK_LENGTH).substr($normalized, 0, self::COUNTRY_AND_CHECK_LENGTH);
        $remainder = 0;

        foreach (str_split($rearranged) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - self::LETTER_OFFSET) : $character;

            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % self::CHECKSUM_MODULUS;
            }
        }

        return $remainder === self::VALID_REMAINDER;
    }

    public function lastFour(): string
    {
        return substr($this->value, -4);
    }
}
