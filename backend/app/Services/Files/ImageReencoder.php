<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Support\Files\RasterImage;
use GdImage;

final class ImageReencoder
{
    private const int PNG_COMPRESSION = 6;

    private const int JPEG_QUALITY = 90;

    public function reencode(string $contents, RasterImage $image): ?string
    {
        $pixels = $this->decode($contents);

        if (! $pixels instanceof GdImage || imagesx($pixels) !== $image->width || imagesy($pixels) !== $image->height) {
            return null;
        }

        return $this->encode($pixels, $image);
    }

    private function decode(string $contents): ?GdImage
    {
        set_error_handler(static fn (): bool => true);

        try {
            $pixels = imagecreatefromstring($contents);
        } finally {
            restore_error_handler();
        }

        return $pixels instanceof GdImage ? $pixels : null;
    }

    private function encode(GdImage $pixels, RasterImage $image): ?string
    {
        $stream = fopen('php://memory', 'w+b');

        if ($stream === false) {
            return null;
        }

        try {
            $written = $image->isPng() ? $this->writePng($pixels, $stream) : imagejpeg($pixels, $stream, self::JPEG_QUALITY);

            return $written && rewind($stream) ? (stream_get_contents($stream) ?: null) : null;
        } finally {
            fclose($stream);
        }
    }

    /**
     * @param  resource  $stream
     */
    private function writePng(GdImage $pixels, $stream): bool
    {
        imagepalettetotruecolor($pixels);
        imagealphablending($pixels, false);
        imagesavealpha($pixels, true);

        return imagepng($pixels, $stream, self::PNG_COMPRESSION);
    }
}
