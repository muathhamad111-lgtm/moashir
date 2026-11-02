<?php

declare(strict_types=1);

namespace App\Http\Resources\Integrations;

use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StoredFile|null $resource
 */
final class ApplePayAssociationFileResource extends JsonResource
{
    /**
     * @return array{set: bool, size_bytes: int|null, sha256: string|null, uploaded_at: string|null, uploaded_by: array{id: int, name: string}|null}
     */
    public function toArray(Request $request): array
    {
        $file = $this->resource;

        if (! $file instanceof StoredFile) {
            return ['set' => false, 'size_bytes' => null, 'sha256' => null, 'uploaded_at' => null, 'uploaded_by' => null];
        }

        $uploader = $file->uploadedBy;

        return [
            'set' => true,
            'size_bytes' => $file->size_bytes,
            'sha256' => $file->sha256,
            'uploaded_at' => $file->created_at->toIso8601String(),
            'uploaded_by' => $uploader instanceof User && ! $uploader->isSuperAdmin() ? ['id' => $uploader->id, 'name' => $uploader->name] : null,
        ];
    }
}
