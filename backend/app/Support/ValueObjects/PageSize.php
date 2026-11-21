<?php

declare(strict_types=1);

namespace App\Support\ValueObjects;

use InvalidArgumentException;

final readonly class PageSize
{
    private const int PRECISION = 2;

    public float $width;

    public float $height;

    public function __construct(float $width, float $height)
    {
        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('A page size has a positive width and height in points.');
        }

        $this->width = round($width, self::PRECISION);
        $this->height = round($height, self::PRECISION);
    }

    public function key(): string
    {
        return $this->width.'x'.$this->height;
    }

    public function turned(): self
    {
        return new self($this->height, $this->width);
    }
}
