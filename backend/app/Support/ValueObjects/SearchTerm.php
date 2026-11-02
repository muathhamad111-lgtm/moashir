<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use App\Support\Search\SearchTextNormalizer;
use InvalidArgumentException;

final readonly class SearchTerm
{
    public string $value;

    public function __construct(string $text)
    {
        $normalized = SearchTextNormalizer::normalize($text);

        if ($normalized === '') {
            throw new InvalidArgumentException('A search term needs at least one searchable character.');
        }

        $this->value = $normalized;
    }

    public static function tryFrom(string $text): ?self
    {
        return SearchTextNormalizer::normalize($text) === '' ? null : new self($text);
    }

    public function length(): int
    {
        return mb_strlen(str_replace(' ', '', $this->value));
    }

    public function containsPattern(): string
    {
        return '%'.addcslashes($this->value, '\\%_').'%';
    }
}
