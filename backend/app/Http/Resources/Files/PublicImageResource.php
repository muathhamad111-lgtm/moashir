<?php

declare(strict_types=1);

namespace App\Http\Resources\Files;

use App\Models\Files\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StoredFile $resource
 */
final class PublicImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $file = $this->resource;

        return [
            'file_id' => $file->public_id,
            'url' => route('content.files.show', ['asset' => $file->public_id], false),
            'mime_type' => $file->mime_type,
            'size_bytes' => $file->size_bytes,
        ];
    }
}
