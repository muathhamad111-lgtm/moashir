<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class TraceCode
{
    public const string PATTERN = '/^MSR-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}-[0-9A-HJKMNP-TV-Z]{4}$/';

    private const string ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    private const string PREFIX = 'MSR';

    private const int GROUPS = 3;

    private const int GROUP_LENGTH = 4;

    public function __construct(public string $value)
    {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw new InvalidArgumentException('A trace code reads MSR- and three groups of four Crockford base-32 characters.');
        }
    }

    public static function generate(): self
    {
        $groups = [];

        for ($group = 0; $group < self::GROUPS; $group++) {
            $groups[] = self::randomGroup();
        }

        return new self(self::PREFIX.'-'.implode('-', $groups));
    }

    public static function tryFrom(string $value): ?self
    {
        $normalized = mb_strtoupper(trim($value));

        return preg_match(self::PATTERN, $normalized) === 1 ? new self($normalized) : null;
    }

    private static function randomGroup(): string
    {
        $characters = '';

        for ($position = 0; $position < self::GROUP_LENGTH; $position++) {
            $characters .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $characters;
    }
}
