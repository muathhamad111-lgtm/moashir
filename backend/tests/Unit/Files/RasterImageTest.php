<?php

declare(strict_types=1);

use App\Support\Files\RasterImage;

it('reads the type and the size of PNG and JPEG images', function (string $type, string $mimeType, string $extension): void {
    $image = RasterImage::of(logoImageBytes($type, 640, 480));

    expect($image)->toBeInstanceOf(RasterImage::class)
        ->and($image?->mimeType)->toBe($mimeType)
        ->and($image?->width)->toBe(640)
        ->and($image?->height)->toBe(480)
        ->and($image?->extension())->toBe($extension)
        ->and($image?->isPng())->toBe($type === 'png');
})->with([
    'PNG' => ['png', 'image/png', 'png'],
    'JPEG' => ['jpeg', 'image/jpeg', 'jpg'],
]);

it('refuses every other content', function (Closure $contents): void {
    expect(RasterImage::of($contents()))->toBeNull();
})->with([
    'SVG' => [fn (): string => '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="400"></svg>'],
    'GIF' => [fn (): string => "GIF89a\x90\x01\x90\x01\x00\x00\x00;"],
    'text' => [fn (): string => 'شعار جمعية الأمل الخيرية'],
    'empty' => [fn (): string => ''],
]);

it('accepts sides from 200 to 2000 pixels only', function (int $width, int $height, bool $accepted): void {
    expect(RasterImage::of(logoImageBytes('png', $width, $height))?->hasSidesBetween(200, 2000))->toBe($accepted);
})->with([
    'smallest square' => [200, 200, true],
    'wide banner' => [2000, 200, true],
    'one pixel too narrow' => [199, 400, false],
    'one pixel too high' => [400, 2001, false],
]);
