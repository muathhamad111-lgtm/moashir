<?php

declare(strict_types=1);

namespace App\Data\Settings;

use App\Enums\Settings\DerivedBrandColor;
use App\Support\Branding\ContrastRatio;
use InvalidArgumentException;

/**
 * The derived colours the client chose by hand, keyed by DerivedBrandColor value.
 */
final readonly class BrandColorOverrides
{
    /**
     * @var array<string, string>
     */
    private array $colors;

    /**
     * @param  array<array-key, mixed>  $colors
     */
    public function __construct(array $colors = [])
    {
        $normalised = [];

        foreach ($colors as $key => $color) {
            if (DerivedBrandColor::tryFrom((string) $key) === null || ! is_string($color) || ! ContrastRatio::isHex($color)) {
                throw new InvalidArgumentException('A colour override needs a known key and a six-digit hex value.');
            }

            $normalised[(string) $key] = mb_strtolower($color);
        }

        ksort($normalised);
        $this->colors = $normalised;
    }

    public function get(DerivedBrandColor $color): ?string
    {
        return $this->colors[$color->value] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->colors;
    }

    public function equals(self $other): bool
    {
        return $this->colors === $other->colors;
    }
}
