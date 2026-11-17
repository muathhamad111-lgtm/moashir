<?php

declare(strict_types=1);

use App\Data\Settings\BrandColors;
use App\Data\Settings\BrandColorScheme;
use App\Data\Settings\ContrastCheck;
use App\Services\Settings\BrandColorDeriver;
use App\Services\Settings\ContrastValidator;
use App\Services\Settings\DefaultBrandColors;
use App\Support\Branding\ColorShade;
use App\Support\Branding\ContrastRatio;

it('computes the WCAG contrast ratio of two colours', function (): void {
    expect(ContrastRatio::format(ContrastRatio::between('#000000', '#ffffff')))->toBe('21.00')
        ->and(ContrastRatio::format(ContrastRatio::between('#ffffff', '#ffffff')))->toBe('1.00')
        ->and(ContrastRatio::format(ContrastRatio::between('#022426', '#3de9ad')))->toBe(ContrastRatio::format(ContrastRatio::between('#3de9ad', '#022426')));
});

it('refuses colours that are not six-digit hex values', function (): void {
    ContrastRatio::between('red', '#ffffff');
})->throws(InvalidArgumentException::class);

it('darkens a colour toward black by the given share', function (): void {
    expect(ColorShade::darken('#ffffff', 0.5))->toBe('#808080')
        ->and(ColorShade::darken('#98d8d3', 0.0))->toBe('#98d8d3')
        ->and(ColorShade::darken('#98d8d3', 1.0))->toBe('#000000');
});

it('normalises brand colours to lower case and refuses invalid values', function (): void {
    expect((new BrandColors('#D4088C', '#4A2A7D'))->primary)->toBe('#d4088c');

    new BrandColors('#d4088c', 'url(x)');
})->throws(InvalidArgumentException::class);

it('picks the readable text colour on each fill and a readable text shade of the primary colour', function (string $primary, string $secondary): void {
    $scheme = app(BrandColorDeriver::class)->scheme(new BrandColors($primary, $secondary));

    expect(app(ContrastValidator::class)->failures($scheme))->toBe([])
        ->and(ContrastRatio::between($scheme->onPrimary, $scheme->primary))->toBeGreaterThanOrEqual(4.5)
        ->and(ContrastRatio::between($scheme->primaryText, '#f8f8f8'))->toBeGreaterThanOrEqual(4.5)
        ->and(ContrastRatio::between($scheme->onSecondary, $scheme->secondary))->toBeGreaterThanOrEqual(4.5);
})->with([
    'brand default' => ['#98d8d3', '#0a2325'],
    'TTS default' => ['#d4088c', '#4a2a7d'],
    'very light' => ['#fff8e1', '#f0f0f0'],
    'very dark' => ['#050505', '#101010'],
]);

it('keeps the shipped default colours at AA contrast', function (): void {
    $scheme = app(BrandColorDeriver::class)->scheme(app(DefaultBrandColors::class)->get());

    expect(app(ContrastValidator::class)->failures($scheme))->toBe([]);
});

it('reports each failing pair with its field, ratio and the required minimum', function (): void {
    $failures = app(ContrastValidator::class)->failures(new BrandColorScheme('#777777', '#ffffff', '#eeeeee', '#777777', '#100f10'));

    expect(array_map(static fn (ContrastCheck $check): string => $check->pair, $failures))->toBe([
        'primary.text_on_fill', 'primary.text_on_content', 'secondary.text_on_fill',
    ])->and(array_map(static fn (ContrastCheck $check): string => $check->field(), $failures))->toBe([
        'primary_color', 'primary_color', 'secondary_color',
    ])->and($failures[0]->minimum)->toBe('4.5')
        ->and((float) $failures[0]->ratio)->toBeLessThan(4.5);
});
