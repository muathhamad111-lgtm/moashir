<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Files\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StoredFile $resource
 */
final class EntityDocumentResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, mime_type: string, size_bytes: int, uploaded_at: string}
     */
    public function toArray(Request $request): array
    {
        $document = $this->resource;

        return [
            'id' => $document->public_id,
            'name' => $document->original_name,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'uploaded_at' => $document->created_at->toIso8601String(),
        ];
    }
}
