<?php

declare(strict_types=1);

namespace App\Http\Resources\Files;

use App\Data\Files\SignedDownload;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SignedDownload $resource
 */
final class SignedDownloadResource extends JsonResource
{
    /**
     * @return array{url: string, expires_at: string, file_name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'url' => $this->resource->url,
            'expires_at' => $this->resource->expiresAt->toIso8601String(),
            'file_name' => $this->resource->fileName,
        ];
    }
}
