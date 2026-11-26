<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class PhoneNumber
{
    public const string SAUDI_MOBILE_PATTERN = '/^\+9665\d{8}$/';

    private const string LOCAL_INPUT_PATTERN = '/^(?:\+?966|00966|0)?(5\d{8})$/';

    public function __construct(public string $value)
    {
        if (preg_match(self::SAUDI_MOBILE_PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('Invalid Saudi mobile number.');
        }
    }

    public static function tryFromSaudiInput(string $input): ?self
    {
        if (preg_match(self::LOCAL_INPUT_PATTERN, $input, $matches) !== 1) {
            return null;
        }

        return new self('+966'.$matches[1]);
    }

    public function masked(): string
    {
        return mb_substr($this->value, 0, 5).'*****'.mb_substr($this->value, -3);
    }
}
