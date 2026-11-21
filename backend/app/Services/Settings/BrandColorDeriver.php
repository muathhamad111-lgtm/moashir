<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\BrandColors;
use App\Data\Settings\BrandColorScheme;
use App\Support\Branding\ColorShade;
use App\Support\Branding\ContrastRatio;
use Illuminate\Contracts\Config\Repository;

final readonly class BrandColorDeriver
{
    public function __construct(private Repository $config) {}

    public function scheme(BrandColors $colors): BrandColorScheme
    {
        return new BrandColorScheme(
            $colors->primary,
            $this->readableOn($colors->primary),
            $this->textOnContent($colors->primary),
            $colors->secondary,
            $this->readableOn($colors->secondary),
        );
    }

    private function readableOn(string $fill): string
    {
        $paper = $this->surface('paper');
        $ink = $this->surface('ink');

        return ContrastRatio::between($paper, $fill) >= ContrastRatio::between($ink, $fill) ? $paper : $ink;
    }

    private function textOnContent(string $color): string
    {
        $content = $this->surface('content');
        $step = $this->config->float('moashir.branding.shade_step');
        $shade = $color;

        for ($amount = $step; ! $this->isReadable($shade, $content) && $amount <= 1.0; $amount += $step) {
            $shade = ColorShade::darken($color, $amount);
        }

        return $shade;
    }

    private function isReadable(string $foreground, string $background): bool
    {
        return ContrastRatio::meets(ContrastRatio::between($foreground, $background), $this->config->string('moashir.branding.minimum_contrast'));
    }

    private function surface(string $name): string
    {
        return mb_strtolower($this->config->string('moashir.branding.surfaces.'.$name));
    }
}
