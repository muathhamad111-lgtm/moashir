<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\BrandColorScheme;
use App\Data\Settings\ContrastCheck;
use App\Support\Branding\ContrastRatio;
use Illuminate\Contracts\Config\Repository;

final readonly class ContrastValidator
{
    public function __construct(private Repository $config) {}

    /**
     * @return list<ContrastCheck>
     */
    public function check(BrandColorScheme $scheme): array
    {
        return [
            $this->pair('primary.text_on_fill', $scheme->onPrimary, $scheme->primary),
            $this->pair('primary.text_on_content', $scheme->primaryText, $this->surface('content')),
            $this->pair('primary.text_on_hover', $scheme->onPrimary, $scheme->primaryHover),
            $this->pair('primary.text_on_soft', $scheme->primaryText, $scheme->primarySoft),
            $this->pair('secondary.text_on_fill', $scheme->onSecondary, $scheme->secondary),
        ];
    }

    /**
     * @return list<ContrastCheck>
     */
    public function failures(BrandColorScheme $scheme): array
    {
        return array_values(array_filter($this->check($scheme), static fn (ContrastCheck $check): bool => ! $check->passes));
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
