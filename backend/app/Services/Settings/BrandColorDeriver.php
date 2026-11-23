<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\BrandColors;
use App\Data\Settings\BrandColorScheme;
use App\Enums\Settings\DerivedBrandColor;
use App\Support\Branding\ColorShade;
use App\Support\Branding\ContrastRatio;
use Illuminate\Contracts\Config\Repository;

/**
 * Derives every brand colour the interface uses from the two base colours, then applies the
 * client's overrides; the sidebar gradient comes from BrandRailDeriver. The SPA mirrors these
 * rules for its live preview only; this class is the authority
 * (tests/Fixtures/branding/scheme-vectors.json keeps both in step).
 */
final readonly class BrandColorDeriver
{
    public function __construct(private Repository $config, private BrandRailDeriver $rail) {}

    public function scheme(BrandColors $colors): BrandColorScheme
    {
        $overrides = $colors->overrides;
        $hover = $overrides->get(DerivedBrandColor::PrimaryHover)
            ?? ColorShade::mix($colors->primary, $this->surface('ink'), $this->config->float('moashir.branding.hover_share'));
        $soft = $overrides->get(DerivedBrandColor::PrimarySoft)
            ?? ColorShade::mix($colors->primary, $this->surface('paper'), $this->config->float('moashir.branding.soft_share'));

        return new BrandColorScheme(
            $colors->primary,
            $overrides->get(DerivedBrandColor::OnPrimary) ?? $this->readableOn($colors->primary, $hover),
            $overrides->get(DerivedBrandColor::PrimaryText) ?? $this->textReadableOn($colors->primary, $soft),
            $hover,
            $soft,
            $colors->secondary,
            $overrides->get(DerivedBrandColor::OnSecondary) ?? $this->readableOn($colors->secondary),
            $this->rail->rail($colors),
        );
    }

    /**
     * Paper or ink, whichever stays more readable on every given fill (its weakest pair wins).
     */
    private function readableOn(string $fill, string ...$moreFills): string
    {
        $fills = [$fill, ...array_values($moreFills)];
        $paper = $this->surface('paper');
        $ink = $this->surface('ink');

        return ContrastRatio::weakest($paper, $fills) >= ContrastRatio::weakest($ink, $fills) ? $paper : $ink;
    }

    /**
     * The colour itself, darkened step by step until it reads on the page background and on the
     * soft brand background.
     */
    private function textReadableOn(string $color, string $soft): string
    {
        $backgrounds = [$this->surface('content'), $soft];
        $step = $this->config->float('moashir.branding.shade_step');
        $shade = $color;

        for ($amount = $step; ! $this->isReadable($shade, $backgrounds) && $amount <= 1.0; $amount += $step) {
            $shade = ColorShade::darken($color, $amount);
        }

        return $shade;
    }

    /**
     * @param  non-empty-list<string>  $backgrounds
     */
    private function isReadable(string $foreground, array $backgrounds): bool
    {
        return ContrastRatio::meets(ContrastRatio::weakest($foreground, $backgrounds), $this->config->string('moashir.branding.minimum_contrast'));
    }

    private function surface(string $name): string
    {
        return mb_strtolower($this->config->string('moashir.branding.surfaces.'.$name));
    }
}
