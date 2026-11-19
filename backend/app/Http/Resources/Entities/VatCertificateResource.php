<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Files\StoredFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StoredFile $resource
 */
final class VatCertificateResource extends JsonResource
{
    /**
     * @return array{id: string, name: string, size_bytes: int, uploaded_at: string}
     */
    public function toArray(Request $request): array
    {
        $certificate = $this->resource;

        return [
            'id' => $certificate->public_id,
            'name' => $certificate->original_name,
            'size_bytes' => $certificate->size_bytes,
            'uploaded_at' => $certificate->created_at->toIso8601String(),
        ];
    }
}
