<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\ContrastCheck;
use App\Data\Settings\PaletteColor;
use App\Support\Branding\ContrastRatio;
use Illuminate\Contracts\Config\Repository;

final readonly class ContrastValidator
{
    public function __construct(private Repository $config) {}

    /**
     * @return list<ContrastCheck>
     */
    public function check(PaletteColor $color): array
    {
        $onAccent = $this->surface('on_accent');

        return [
            $this->pair('light.accent_text_on_content', $color->textOnLight, $this->surface('light_content')),
            $this->pair('light.text_on_accent', $onAccent, $color->fill),
            $this->pair('dark.accent_on_content', $color->fill, $this->surface('dark_content')),
            $this->pair('dark.text_on_accent', $onAccent, $color->fill),
        ];
    }

    /**
     * @return list<ContrastCheck>
     */
    public function failures(PaletteColor $color): array
    {
        return array_values(array_filter($this->check($color), static fn (ContrastCheck $check): bool => ! $check->passes));
    }

    private function pair(string $name, string $foreground, string $background): ContrastCheck
    {
        $minimum = $this->config->string('moashir.branding.minimum_contrast');
        $ratio = ContrastRatio::between($foreground, $background);

        return new ContrastCheck($name, $foreground, $background, ContrastRatio::format($ratio), $minimum, ContrastRatio::meets($ratio, $minimum));
    }

    private function surface(string $name): string
    {
        return mb_strtolower($this->config->string('moashir.branding.surfaces.'.$name));
    }
}
