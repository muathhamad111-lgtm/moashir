<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use GdImage;

final class ImageOrientation
{
    private const array ROTATIONS = [3 => 180, 6 => -90, 8 => 90];

    private const array MIRRORED = [2 => 0, 4 => 180, 5 => 90, 7 => -90];

    public function upright(GdImage $pixels, string $path, bool $isPng): GdImage
    {
        $orientation = $isPng ? 1 : $this->orientationOf($path);
        $angle = self::ROTATIONS[$orientation] ?? self::MIRRORED[$orientation] ?? 0;

        if (array_key_exists($orientation, self::MIRRORED)) {
            imageflip($pixels, IMG_FLIP_HORIZONTAL);
        }

        if ($angle === 0) {
            return $pixels;
        }

        $rotated = imagerotate($pixels, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $pixels;
    }

    private function orientationOf(string $path): int
    {
        if (! function_exists('exif_read_data')) {
            return 1;
        }

        set_error_handler(static fn (): bool => true);

        try {
            $exif = exif_read_data($path, 'IFD0');
        } finally {
            restore_error_handler();
        }

        return is_array($exif) && is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;
    }
}
