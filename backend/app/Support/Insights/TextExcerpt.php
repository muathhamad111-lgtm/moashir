<?php

declare(strict_types=1);

namespace App\Support\Insights;

final readonly class TextExcerpt
{
    private function __construct(public string $text, public bool $isTruncated) {}

    public static function of(string $text, int $limit): self
    {
        $text = trim($text);

        if (mb_strlen($text) <= $limit) {
            return new self($text, false);
        }

        $cut = mb_substr($text, 0, $limit);
        $boundary = mb_strrpos($cut, ' ');

        return new self(rtrim($boundary === false || $boundary === 0 ? $cut : mb_substr($cut, 0, $boundary)), true);
    }
}
