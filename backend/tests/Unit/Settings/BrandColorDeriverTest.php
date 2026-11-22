<?php

declare(strict_types=1);

use App\Data\Settings\BrandColorOverrides;
use App\Data\Settings\BrandColors;
use App\Services\Settings\BrandColorDeriver;
use App\Services\Settings\DefaultBrandColors;
use App\Support\Branding\ColorShade;
use App\Support\Branding\ContrastRatio;

it('computes the WCAG contrast ratio of two colours', function (): void {
    expect(ContrastRatio::between('#000000', '#ffffff'))->toEqualWithDelta(21.0, 0.001)
        ->and(ContrastRatio::between('#ffffff', '#ffffff'))->toEqualWithDelta(1.0, 0.001)
        ->and(ContrastRatio::between('#022426', '#3de9ad'))->toEqualWithDelta(ContrastRatio::between('#3de9ad', '#022426'), 0.000001);
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

    expect(ContrastRatio::between($scheme->onPrimary, $scheme->primary))->toBeGreaterThanOrEqual(4.5)
        ->and(ContrastRatio::between($scheme->primaryText, '#f8f8f8'))->toBeGreaterThanOrEqual(4.5)
        ->and(ContrastRatio::between($scheme->onSecondary, $scheme->secondary))->toBeGreaterThanOrEqual(4.5)
        ->and(ContrastRatio::between($scheme->onPrimary, $scheme->primaryHover))->toBeGreaterThanOrEqual(4.5)
        ->and(ContrastRatio::between($scheme->primaryText, $scheme->primarySoft))->toBeGreaterThanOrEqual(4.5);
})->with([
    'brand default' => ['#98d8d3', '#0a2325'],
    'TTS default' => ['#d4088c', '#4a2a7d'],
    'very light' => ['#fff8e1', '#f0f0f0'],
    'very dark' => ['#050505', '#101010'],
]);

it('derives readable defaults from the shipped default colours', function (): void {
    $scheme = app(BrandColorDeriver::class)->scheme(app(DefaultBrandColors::class)->get());

    expect(ContrastRatio::between($scheme->onPrimary, $scheme->primary))->toBeGreaterThanOrEqual(4.5)
        ->and(ContrastRatio::between($scheme->primaryText, '#f8f8f8'))->toBeGreaterThanOrEqual(4.5)
        ->and(ContrastRatio::between($scheme->onSecondary, $scheme->secondary))->toBeGreaterThanOrEqual(4.5);
});

it('keeps a client override exactly as chosen, even when its contrast is low', function (): void {
    $scheme = app(BrandColorDeriver::class)->scheme(new BrandColors('#ffe066', '#0a2325', new BrandColorOverrides(['on_primary' => '#FFFFFF'])));

    expect($scheme->onPrimary)->toBe('#ffffff')
        ->and(ContrastRatio::between($scheme->onPrimary, $scheme->primary))->toBeLessThan(4.5);
});

it('mixes two colours in sRGB like CSS color-mix', function (): void {
    expect(ColorShade::mix('#98d8d3', '#100f10', 0.88))->toBe('#88c0bc')
        ->and(ColorShade::mix('#98d8d3', '#ffffff', 0.1))->toBe('#f5fbfb')
        ->and(ColorShade::mix('#000000', '#ffffff', 1.0))->toBe('#000000');
});

it('derives the same schemes as the shared test vectors the SPA preview is checked against', function (array $vector): void {
    $colors = new BrandColors($vector['primary'], $vector['secondary'], new BrandColorOverrides($vector['overrides']));

    expect(app(BrandColorDeriver::class)->scheme($colors)->toArray())->toBe($vector['scheme']);
})->with(function (): array {
    $fixture = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/Fixtures/branding/scheme-vectors.json'), true, 8, JSON_THROW_ON_ERROR);

    return array_combine(
        array_column($fixture['vectors'], 'label'),
        array_map(static fn (array $vector): array => [$vector], $fixture['vectors']),
    );
});

it('lets an override replace only its own derived colour', function (): void {
    $scheme = app(BrandColorDeriver::class)->scheme(new BrandColors('#98d8d3', '#0a2325', new BrandColorOverrides(['on_secondary' => '#D9F7F7'])));

    expect($scheme->onSecondary)->toBe('#d9f7f7')
        ->and($scheme->onPrimary)->toBe('#100f10')
        ->and($scheme->primaryText)->toBe('#547774');
});

it('refuses an override with an unknown key or a value that is not a hex colour', function (array $overrides): void {
    new BrandColorOverrides($overrides);
})->throws(InvalidArgumentException::class)->with([
    'unknown key' => [['navbar' => '#ffffff']],
    'named colour' => [['on_primary' => 'white']],
    'not a string' => [['on_primary' => 255]],
]);

it('compares overrides regardless of key order or letter case', function (): void {
    $first = new BrandColors('#98d8d3', '#0a2325', new BrandColorOverrides(['primary_soft' => '#E6F4F3', 'on_primary' => '#100f10']));
    $second = new BrandColors('#98d8d3', '#0a2325', new BrandColorOverrides(['on_primary' => '#100f10', 'primary_soft' => '#e6f4f3']));

    expect($first->equals($second))->toBeTrue()
        ->and($first->equals(new BrandColors('#98d8d3', '#0a2325')))->toBeFalse();
});
