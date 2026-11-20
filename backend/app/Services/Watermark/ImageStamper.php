<?php

declare(strict_types=1);

namespace App\Services\Watermark;

use App\Contracts\Watermark\StampRasterizer;
use App\Data\Watermark\ViewerStamp;
use App\Exceptions\Watermark\WatermarkFailed;
use App\Support\ValueObjects\PageSize;
use GdImage;
use Illuminate\Contracts\Config\Repository;

final readonly class ImageStamper
{
    private const float POINTS_PER_PIXEL = 0.75;

    private const int PNG_COMPRESSION = 6;

    public function __construct(
        private StampSheetRenderer $renderer,
        private StampRasterizer $rasterizer,
        private ImageOrientation $orientation,
        private Repository $config,
    ) {}

    public function stamp(string $source, bool $isPng, ViewerStamp $stamp, WatermarkWorkspace $workspace): string
    {
        $pixels = $this->orientation->upright($this->decode($source, $isPng), $source, $isPng);
        [$width, $height] = [imagesx($pixels), imagesy($pixels)];

        if ($width * $height > $this->config->integer('moashir.watermark.image_max_pixels')) {
            throw WatermarkFailed::unreadable();
        }

        $stampPdf = $workspace->path('stamp.pdf');
        file_put_contents($stampPdf, $this->renderer->render($stamp, [new PageSize($width * self::POINTS_PER_PIXEL, $height * self::POINTS_PER_PIXEL)]));
        $overlay = $this->decode($this->rasterizer->rasterize($stampPdf, $width, $height, $workspace->path('stamp')), true);

        imagealphablending($pixels, true);
        imagecopy($pixels, $overlay, 0, 0, 0, 0, $width, $height);

        return $this->encode($pixels, $isPng, $workspace->path($isPng ? 'stamped.png' : 'stamped.jpg'));
    }

    private function decode(string $path, bool $isPng): GdImage
    {
        set_error_handler(static fn (): bool => true);

        try {
            $pixels = $isPng ? imagecreatefrompng($path) : imagecreatefromjpeg($path);
        } finally {
            restore_error_handler();
        }

        return $pixels instanceof GdImage ? $pixels : throw WatermarkFailed::unreadable();
    }

    private function encode(GdImage $pixels, bool $isPng, string $output): string
    {
        if ($isPng) {
            imagesavealpha($pixels, true);
        }

        $written = $isPng ? imagepng($pixels, $output, self::PNG_COMPRESSION) : imagejpeg($pixels, $output, $this->config->integer('moashir.watermark.jpeg_quality'));

        return $written ? $output : throw WatermarkFailed::unreadable();
    }
}
