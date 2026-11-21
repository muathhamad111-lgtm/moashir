<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Files\StoredFile;
use App\Models\Identity\User;
use App\Services\Files\SignedDownloadIssuer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read StoredFile $resource
 */
final class EntityLogoResource extends JsonResource
{
    /**
     * @return array{id: string, url: string|null, uploaded_at: string}
     */
    public function toArray(Request $request): array
    {
        $logo = $this->resource;
        $viewer = $request->user();

        return [
            'id' => $logo->public_id,
            'url' => $viewer instanceof User && $logo->isScannedClean() ? app(SignedDownloadIssuer::class)->issue($logo, $viewer)->url : null,
            'uploaded_at' => $logo->created_at->toIso8601String(),
        ];
    }
}
