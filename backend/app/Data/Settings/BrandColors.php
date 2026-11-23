<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Support\Branding\ContrastRatio;
use InvalidArgumentException;

final readonly class BrandColors
{
    public string $primary;

    public string $secondary;

    public function __construct(
        string $primary,
        string $secondary,
        public BrandColorOverrides $overrides = new BrandColorOverrides,
        public BrandRailColors $rail = new BrandRailColors,
    ) {
        if (! ContrastRatio::isHex($primary) || ! ContrastRatio::isHex($secondary)) {
            throw new InvalidArgumentException('Brand colours must be six-digit hex values.');
        }

        $this->primary = mb_strtolower($primary);
        $this->secondary = mb_strtolower($secondary);
    }

    public function equals(self $other): bool
    {
        return $this->primary === $other->primary
            && $this->secondary === $other->secondary
            && $this->overrides->equals($other->overrides)
            && $this->rail->equals($other->rail);
    }
}
