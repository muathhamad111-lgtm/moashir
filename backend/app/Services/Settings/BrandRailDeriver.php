<?php

declare(strict_types=1);

namespace App\Services\Settings;

use App\Data\Settings\BrandColors;
use App\Data\Settings\BrandRailScheme;
use App\Support\Branding\ColorShade;
use App\Support\Branding\ContrastRatio;
use Illuminate\Contracts\Config\Repository;

/**
 * Works out the sidebar gradient from its two colours (or the automatic ones) and its default
 * text colours: text, muted text and a brand accent chosen to read on all of it, plus how
 * strongly the primary glows at the bottom. These are defaults, not a check: the client may
 * pick any two colours (operator decision, 2026-11-22). The SPA mirrors these rules (utils/brandRail.js); the shared
 * test vectors keep both in step.
 */
final readonly class BrandRailDeriver
{
    public function __construct(private Repository $config) {}

    public function rail(BrandColors $colors): BrandRailScheme
    {
        $top = $colors->rail->top ?? $colors->secondary;
        $bottom = $colors->rail->bottom
            ?? ColorShade::blend($colors->secondary, $colors->primary, $this->config->float('moashir.branding.rail_auto_bottom_share'));
        $stops = $this->stops($top, $bottom);
        $text = $this->readableOn($stops);
        $glow = $this->glowPercent($text, $stops, $colors->primary, $bottom);
        $fills = [...$stops, $this->glowFill($colors->primary, $bottom, $glow)];

        return new BrandRailScheme(
            $top,
            $bottom,
            $stops,
            $text,
            $this->muted($text, $stops[intdiv(count($stops), 2)], $fills),
            $this->accent($colors->primary, $text, $fills),
            $glow,
        );
    }

    /**
     * @return non-empty-list<string>
     */
    private function stops(string $top, string $bottom): array
    {
        $count = $this->config->integer('moashir.branding.rail_stops');

        return array_map(static fn (int $index): string => ColorShade::blend($top, $bottom, $index / $count), range(0, $count));
    }

    /**
     * Paper or ink, whichever keeps the higher contrast on its weakest stop.
     *
     * @param  non-empty-list<string>  $stops
     */
    private function readableOn(array $stops): string
    {
        $paper = $this->surface('paper');
        $ink = $this->surface('ink');

        return ContrastRatio::weakest($paper, $stops) >= ContrastRatio::weakest($ink, $stops) ? $paper : $ink;
    }

    /**
     * The strongest glow, up to the look's own, under which the text still reads.
     *
     * @param  non-empty-list<string>  $stops
     */
    private function glowPercent(string $text, array $stops, string $primary, string $bottom): int
    {
        $step = $this->config->integer('moashir.branding.rail_glow_step_percent');

        for ($percent = $this->config->integer('moashir.branding.rail_glow_max_percent'); $percent > 0; $percent -= $step) {
            if ($this->reads($text, [...$stops, $this->glowFill($primary, $bottom, $percent)])) {
                return $percent;
            }
        }

        return 0;
    }

    /**
     * Inactive items and captions: the text pulled toward the middle of the gradient as far as it
     * still reads on every fill, the glow included (menu items can reach the bottom edge).
     *
     * @param  non-empty-list<string>  $fills
     */
    private function muted(string $text, string $middle, array $fills): string
    {
        $step = $this->config->integer('moashir.branding.rail_muted_step_percent');

        for ($percent = $this->config->integer('moashir.branding.rail_muted_max_percent'); $percent > 0; $percent -= $step) {
            $candidate = ColorShade::mix($middle, $text, $percent / 100);

            if ($this->reads($candidate, $fills)) {
                return $candidate;
            }
        }

        return $text;
    }

    /**
     * The primary as brand-coloured text on the rail, moved toward the text colour only as far as
     * it must to read on every fill.
     *
     * @param  non-empty-list<string>  $fills
     */
    private function accent(string $primary, string $text, array $fills): string
    {
        $step = $this->config->integer('moashir.branding.rail_accent_step_percent');

        for ($percent = 0; $percent < 100; $percent += $step) {
            $candidate = ColorShade::blend($primary, $text, $percent / 100);

            if ($this->reads($candidate, $fills)) {
                return $candidate;
            }
        }

        return $text;
    }

    private function glowFill(string $primary, string $bottom, int $percent): string
    {
        return ColorShade::mix($primary, $bottom, $percent / 100);
    }

    /**
     * @param  non-empty-list<string>  $fills
     */
    private function reads(string $foreground, array $fills): bool
    {
        return ContrastRatio::meets(ContrastRatio::weakest($foreground, $fills), $this->config->string('moashir.branding.minimum_contrast'));
    }

    private function surface(string $name): string
    {
        return mb_strtolower($this->config->string('moashir.branding.surfaces.'.$name));
    }
}
