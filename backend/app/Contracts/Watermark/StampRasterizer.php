<?php

declare(strict_types=1);

namespace App\Contracts\Watermark;

interface StampRasterizer
{
    public function rasterize(string $stampPdf, int $width, int $height, string $outputBase): string;
}
