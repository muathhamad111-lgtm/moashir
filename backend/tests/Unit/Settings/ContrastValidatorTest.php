<?php

declare(strict_types=1);

use App\Data\Settings\ContrastCheck;
use App\Data\Settings\PaletteColor;
use App\Enums\Settings\AccentToken;
use App\Services\Settings\BrandPalette;
use App\Services\Settings\ContrastValidator;
use App\Support\Branding\ContrastRatio;

it('computes the WCAG contrast ratio of two colours', function (): void {
    expect(ContrastRatio::format(ContrastRatio::between('#000000', '#ffffff')))->toBe('21.00')
        ->and(ContrastRatio::format(ContrastRatio::between('#ffffff', '#ffffff')))->toBe('1.00')
        ->and(ContrastRatio::format(ContrastRatio::between('#022426', '#3de9ad')))->toBe(ContrastRatio::format(ContrastRatio::between('#3de9ad', '#022426')));
});

it('refuses colours that are not six-digit hex values', function (): void {
    ContrastRatio::between('red', '#ffffff');
})->throws(InvalidArgumentException::class);

it('keeps every colour of the shipped palette at AA contrast in light and dark mode', function (): void {
    $palette = app(BrandPalette::class)->colors();
    $validator = app(ContrastValidator::class);

    expect($palette)->toHaveCount(count(AccentToken::cases()));

    foreach ($palette as $color) {
        expect($validator->failures($color))->toBe([], 'Palette token '.$color->token->value.' fails the contrast check.');
    }
});

it('reports each failing pair with its ratio and the required minimum', function (): void {
    $failures = app(ContrastValidator::class)->failures(new PaletteColor(AccentToken::Amber, '#0f2a2b', '#f0f0f0'));

    expect(array_map(static fn (ContrastCheck $check): string => $check->pair, $failures))->toBe([
        'light.accent_text_on_content', 'light.text_on_accent', 'dark.accent_on_content', 'dark.text_on_accent',
    ])->and($failures[0]->minimum)->toBe('4.5')
        ->and((float) $failures[0]->ratio)->toBeLessThan(4.5);
});
