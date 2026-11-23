<?php

declare(strict_types=1);

use App\Data\Settings\BrandColorOverrides;
use App\Data\Settings\BrandColors;
use App\Data\Settings\BrandRailColors;
use App\Data\Settings\BrandRailScheme;
use App\Services\Settings\BrandRailDeriver;
use App\Support\Branding\ColorShade;
use App\Support\Branding\ContrastRatio;

function sidebarRail(?string $top, ?string $bottom): BrandRailScheme
{
    return app(BrandRailDeriver::class)->rail(new BrandColors('#98d8d3', '#0a2325', new BrandColorOverrides, new BrandRailColors($top, $bottom)));
}

/**
 * Every colour the rail text can sit on: each stop and the glow at the strength the rail uses.
 *
 * @return non-empty-list<string>
 */
function sidebarRailFills(BrandRailScheme $rail): array
{
    return [...$rail->stops, ColorShade::mix('#98d8d3', $rail->bottom, $rail->glowPercent / 100)];
}

it('blends two colours in OKLab and keeps both ends exactly', function (): void {
    expect(ColorShade::blend('#1D2B4F', '#3c6e71', 0.0))->toBe('#1d2b4f')
        ->and(ColorShade::blend('#1d2b4f', '#3c6e71', 1.0))->toBe('#3c6e71')
        ->and(ColorShade::blend('#0a2325', '#98d8d3', 0.26))->toBe('#2b4d4d');
});

it('keeps the middle of a blend brighter than an sRGB mix of two saturated colours', function (): void {
    $perceptual = ColorShade::blend('#0000ff', '#ffff00', 0.5);
    $plain = ColorShade::mix('#0000ff', '#ffff00', 0.5);

    expect(ContrastRatio::between($perceptual, '#000000'))->toBeGreaterThan(ContrastRatio::between($plain, '#000000'));
});

it('follows the brand colours while no sidebar colour is chosen', function (): void {
    $rail = sidebarRail(null, null);

    expect($rail->top)->toBe('#0a2325')
        ->and($rail->bottom)->toBe('#2b4d4d')
        ->and($rail->stops)->toHaveCount(9)
        ->and($rail->stops[0])->toBe('#0a2325')
        ->and($rail->stops[8])->toBe('#2b4d4d')
        ->and($rail->onRail)->toBe('#ffffff');
});

it('picks dark text on a light gradient and white text on a dark one', function (): void {
    expect(sidebarRail('#f3f6f4', '#cfe7e4')->onRail)->toBe('#100f10')
        ->and(sidebarRail('#1d2b4f', '#3c6e71')->onRail)->toBe('#ffffff');
});

it('picks default text, muted text and accent colours that read on every stop and under the glow', function (?string $top, ?string $bottom): void {
    $rail = sidebarRail($top, $bottom);
    $fills = sidebarRailFills($rail);

    expect(ContrastRatio::weakest($rail->onRail, $fills))->toBeGreaterThanOrEqual(4.5)
        ->and(ContrastRatio::weakest($rail->onRailMuted, $fills))->toBeGreaterThanOrEqual(4.5)
        ->and(ContrastRatio::weakest($rail->accent, $fills))->toBeGreaterThanOrEqual(4.5);
})->with([
    'automatic' => [null, null],
    'navy to teal' => ['#1d2b4f', '#3c6e71'],
    'light pair' => ['#f3f6f4', '#cfe7e4'],
    'top only' => ['#192138', null],
]);

it('dims the primary glow only as far as the text needs', function (): void {
    expect(sidebarRail(null, null)->glowPercent)->toBe(34)
        ->and(sidebarRail('#f3f6f4', '#cfe7e4')->glowPercent)->toBe(40)
        ->and(sidebarRail('#2b1055', '#9fb8ec')->glowPercent)->toBe(0);
});

it('still derives a rail for any two colours the client picks, with no readable text guaranteed', function (): void {
    $rail = sidebarRail('#2b1055', '#9fb8ec');

    expect($rail->onRail)->toBe('#ffffff')
        ->and($rail->glowPercent)->toBe(0)
        ->and(ContrastRatio::weakest($rail->onRail, $rail->stops))->toBeLessThan(4.5);
});

it('normalises the sidebar colours to lower case and refuses anything but six-digit hex', function (): void {
    $rail = new BrandRailColors('#1D2B4F');

    expect($rail->top)->toBe('#1d2b4f')
        ->and($rail->bottom)->toBeNull()
        ->and($rail->equals(new BrandRailColors('#1d2b4f')))->toBeTrue();

    new BrandRailColors('navy');
})->throws(InvalidArgumentException::class);
