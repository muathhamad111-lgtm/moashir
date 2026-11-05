<?php

declare(strict_types=1);

use App\Services\Files\ImageReencoder;
use App\Support\Files\RasterImage;

it('writes a fresh image of the same type and size without the hidden metadata', function (Closure $contents, string $mimeType): void {
    $original = $contents();
    $image = RasterImage::of($original);
    assert($image instanceof RasterImage);

    $reencoded = (string) app(ImageReencoder::class)->reencode($original, $image);

    expect($original)->toContain(LOGO_HIDDEN_NOTE)
        ->and($reencoded)->not->toContain(LOGO_HIDDEN_NOTE)
        ->and(RasterImage::of($reencoded)?->mimeType)->toBe($mimeType)
        ->and(getimagesizefromstring($reencoded))->toMatchArray([0 => 400, 1 => 300]);
})->with([
    'PNG with a text chunk' => [fn (): string => pngWithTextChunk(logoImageBytes(), LOGO_HIDDEN_NOTE), 'image/png'],
    'JPEG with a comment' => [fn (): string => jpegWithComment(logoImageBytes('jpeg'), LOGO_HIDDEN_NOTE), 'image/jpeg'],
]);

it('keeps the transparency of a PNG logo', function (): void {
    $canvas = imagecreatetruecolor(300, 300);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, (int) imagecolorallocatealpha($canvas, 0, 0, 0, 127));
    $stream = fopen('php://memory', 'w+b');
    imagepng($canvas, $stream);
    rewind($stream);
    $original = (string) stream_get_contents($stream);
    $image = RasterImage::of($original);
    assert($image instanceof RasterImage);

    $reencoded = imagecreatefromstring((string) app(ImageReencoder::class)->reencode($original, $image));
    assert($reencoded instanceof GdImage);

    expect(imagecolorat($reencoded, 10, 10) >> 24)->toBe(127);
});

it('gives nothing back for an image whose pixels cannot be decoded', function (): void {
    $png = logoImageBytes();
    $broken = substr($png, 0, 60).str_repeat("\x00", 400).substr($png, -12);
    $image = RasterImage::of($broken);
    assert($image instanceof RasterImage);

    expect(app(ImageReencoder::class)->reencode($broken, $image))->toBeNull();
});
