<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class PasswordDigest
{
    private const string ALGORITHM = 'sha256';

    private const string HEX_PATTERN = '/^[0-9a-f]{64}$/';

    public string $value;

    private function __construct(string $value)
    {
        if (! self::isHexDigest($value)) {
            throw new InvalidArgumentException('A password digest must be 64 lowercase hexadecimal characters.');
        }

        $this->value = $value;
    }

    public static function ofPassword(string $password): self
    {
        return new self(hash(self::ALGORITHM, $password));
    }

    public static function fromHex(string $hex): self
    {
        return new self(self::canonical($hex));
    }

    public static function tryFromHex(string $hex): ?self
    {
        $canonical = self::canonical($hex);

        return self::isHexDigest($canonical) ? new self($canonical) : null;
    }

    private static function canonical(string $hex): string
    {
        return mb_strtolower(trim($hex));
    }

    private static function isHexDigest(string $value): bool
    {
        return preg_match(self::HEX_PATTERN, $value) === 1;
    }
}
