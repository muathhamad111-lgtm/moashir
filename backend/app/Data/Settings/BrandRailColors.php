<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Support\Branding\ContrastRatio;
use InvalidArgumentException;

/**
 * The two colours the client chose for the sidebar gradient. A null colour follows the brand
 * colours (BrandRailDeriver works out the automatic one).
 */
final readonly class BrandRailColors
{
    public ?string $top;

    public ?string $bottom;

    public function __construct(?string $top = null, ?string $bottom = null)
    {
        $this->top = $this->normalise($top);
        $this->bottom = $this->normalise($bottom);
    }

    public function equals(self $other): bool
    {
        return $this->top === $other->top && $this->bottom === $other->bottom;
    }

    private function normalise(?string $color): ?string
    {
        if ($color === null) {
            return null;
        }

        if (! ContrastRatio::isHex($color)) {
            throw new InvalidArgumentException('A sidebar colour must be a six-digit hex value.');
        }

        return mb_strtolower($color);
    }
}
