<?php

declare(strict_types=1);

use App\Services\Documents\DocumentAssets;
use Illuminate\Filesystem\Filesystem;

/**
 * Document assets over a filesystem where the licensed Hacen Maghreb files exist or not, so the
 * tests do not depend on the checkout (the public repository has no licensed fonts).
 */
function documentAssetsWithHacen(bool $present): DocumentAssets
{
    $files = Mockery::mock(Filesystem::class);
    $files->allows('exists')->andReturnUsing(fn (string $path): bool => $present || ! str_contains($path, 'hacen-maghreb'));
    $files->allows('get')->andReturnUsing(fn (string $path): string => basename($path));

    return new DocumentAssets($files, app());
}

it('reports a document font as present only when it is known and its file is on disk', function (): void {
    expect(documentAssetsWithHacen(true)->hasFont('hacen-maghreb-regular.ttf'))->toBeTrue()
        ->and(documentAssetsWithHacen(false)->hasFont('hacen-maghreb-regular.ttf'))->toBeFalse()
        ->and(documentAssetsWithHacen(true)->hasFont('unknown-font.ttf'))->toBeFalse();
});

it('embeds Hacen Maghreb for Arabic text, regular up to 500 and bold from 600, when the licensed files exist', function (): void {
    $css = view('documents.partials.fonts', ['assets' => documentAssetsWithHacen(true)])->render();

    expect(substr_count($css, "font-family: 'Moashir Arabic'"))->toBe(2)
        ->and($css)->toContain('base64,'.base64_encode('hacen-maghreb-regular.ttf')."\") format('truetype'); font-weight: 100 500;")
        ->and($css)->toContain('base64,'.base64_encode('hacen-maghreb-bold.ttf')."\") format('truetype'); font-weight: 600 900;")
        ->and($css)->toContain('unicode-range: U+0600-06FF')
        ->and($css)->toContain("font-family: 'Moashir Body'");
});

it('renders the documents with the open-licence Arabic font when the licensed files are absent', function (): void {
    $css = view('documents.partials.fonts', ['assets' => documentAssetsWithHacen(false)])->render();

    expect($css)->not->toContain('Moashir Arabic')
        ->and($css)->toContain('base64,'.base64_encode('IBMPlexSansArabic-Regular.ttf'));
});

it('puts Hacen Maghreb ahead of IBM Plex Sans Arabic in every document font stack', function (): void {
    $tokens = view('documents.partials.tokens')->render();

    expect($tokens)->toContain("--font-body: 'Moashir Latin', 'Moashir Arabic', 'Moashir Body', sans-serif;")
        ->and($tokens)->toContain("--font-heading: 'Moashir Latin', 'Moashir Arabic', 'Moashir Heading', 'Moashir Body', sans-serif;")
        ->and($tokens)->toContain("--font-latin: 'Moashir Latin', 'Moashir Arabic', 'Moashir Body', sans-serif;");
});
