<?php

declare(strict_types=1);

namespace App\Services\Documents;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

final class QrCodeImage
{
    private const int SIZE = 240;

    private const int MARGIN = 0;

    public function dataUri(string $content): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(self::SIZE, self::MARGIN), new SvgImageBackEnd));

        return 'data:image/svg+xml;base64,'.base64_encode($writer->writeString($content, 'UTF-8', ErrorCorrectionLevel::M()));
    }
}
