<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Files;

use App\Actions\Files\ServePublicImage;
use App\Data\Files\FileDownload;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicImageController
{
    private const string VECTOR_POLICY = "default-src 'none'; style-src 'unsafe-inline'; sandbox";

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
            ...$this->vectorHeaders($image),
        ]);
    }

    /**
     * An SVG opened on its own is a document: the sandbox keeps it from running script or loading anything.
     *
     * @return array<string, string>
     */
    private function vectorHeaders(FileDownload $image): array
    {
        return $image->mimeType === ServePublicImage::VECTOR_TYPE ? ['Content-Security-Policy' => self::VECTOR_POLICY] : [];
    }
}
