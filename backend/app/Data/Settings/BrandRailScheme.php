<?php

declare(strict_types=1);

namespace App\Data\Settings;

/**
 * The sidebar gradient and the colours that read on it, as BrandRailDeriver works them out.
 */
final readonly class BrandRailScheme
{
    /**
     * @param  non-empty-list<string>  $stops  the gradient from top to bottom, already blended
     * @param  int  $glowPercent  how strongly the primary glows at the bottom edge (0 to 100)
     */
    public function __construct(
        public string $top,
        public string $bottom,
        public array $stops,
        public string $onRail,
        public string $onRailMuted,
        public string $accent,
        public int $glowPercent,
    ) {}

    /**
     * @return array{rail_top: string, rail_bottom: string, rail_stops: non-empty-list<string>, on_rail: string, on_rail_muted: string, rail_accent: string, rail_glow: int}
     */
    public function toArray(): array
    {
        return [
            'rail_top' => $this->top,
            'rail_bottom' => $this->bottom,
            'rail_stops' => $this->stops,
            'on_rail' => $this->onRail,
            'on_rail_muted' => $this->onRailMuted,
            'rail_accent' => $this->accent,
            'rail_glow' => $this->glowPercent,
        ];
    }
}
