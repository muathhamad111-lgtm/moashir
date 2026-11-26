<?php

declare(strict_types=1);

namespace App\Data\Settings;

final readonly class BrandColorScheme
{
    public function __construct(
        public string $primary,
        public string $onPrimary,
        public string $primaryText,
        public string $primaryHover,
        public string $primarySoft,
        public string $secondary,
        public string $onSecondary,
        public BrandRailScheme $rail,
    ) {}

    /**
     * @return array{primary: string, on_primary: string, primary_text: string, primary_hover: string, primary_soft: string, secondary: string, on_secondary: string, rail_top: string, rail_bottom: string, rail_stops: non-empty-list<string>, on_rail: string, on_rail_muted: string, rail_accent: string, rail_glow: int}
     */
    public function toArray(): array
    {
        return [
            'primary' => $this->primary,
            'on_primary' => $this->onPrimary,
            'primary_text' => $this->primaryText,
            'primary_hover' => $this->primaryHover,
            'primary_soft' => $this->primarySoft,
            'secondary' => $this->secondary,
            'on_secondary' => $this->onSecondary,
            ...$this->rail->toArray(),
        ];
    }
}
