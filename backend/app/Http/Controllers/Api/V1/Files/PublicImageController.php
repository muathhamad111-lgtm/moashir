<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Files;

use App\Actions\Files\ServePublicImage;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicImageController
{
    public function __invoke(string $asset, ServePublicImage $serve): StreamedResponse
    {
        $image = $serve->handle($asset);

        return new StreamedResponse(static function () use ($image): void {
            fpassthru($image->stream);
            fclose($image->stream);
        }, StreamedResponse::HTTP_OK, [
            'Content-Type' => $image->mimeType,
            'Content-Length' => (string) $image->sizeBytes,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $image->fileName),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
