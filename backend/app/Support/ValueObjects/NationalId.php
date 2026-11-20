<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class NationalId
{
    public const string PATTERN = '/^[12][0-9]{9}$/';

    private const int VISIBLE_DIGITS = 4;

    private const string MASK = '•';

    private const array EASTERN_DIGITS = [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ];

    public string $value;

    public function __construct(string $input)
    {
        $normalized = self::normalize($input);

        if (! self::isValid($normalized)) {
            throw new InvalidArgumentException('A national ID or Iqama number has 10 digits, starts with 1 or 2 and carries a valid check digit.');
        }

        $this->value = $normalized;
    }

    public static function tryFrom(string $input): ?self
    {
        return self::isValid(self::normalize($input)) ? new self($input) : null;
    }

    public static function normalize(string $input): string
    {
        return (string) preg_replace('/\s+/u', '', strtr($input, self::EASTERN_DIGITS));
    }

    public static function isValid(string $normalized): bool
    {
        return preg_match(self::PATTERN, $normalized) === 1 && self::checksumOf($normalized) % 10 === 0;
    }

    public function masked(): string
    {
        return str_repeat(self::MASK, strlen($this->value) - self::VISIBLE_DIGITS).substr($this->value, -self::VISIBLE_DIGITS);
    }

    private static function checksumOf(string $digits): int
    {
        $sum = 0;

        foreach (str_split($digits) as $position => $digit) {
            $sum += $position % 2 === 0 ? self::doubledDigitOf((int) $digit) : (int) $digit;
        }

        return $sum;
    }

    private static function doubledDigitOf(int $digit): int
    {
        $doubled = $digit * 2;

        return $doubled > 9 ? $doubled - 9 : $doubled;
    }
}
