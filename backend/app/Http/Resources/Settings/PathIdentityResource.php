<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Paths\PathIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PathIdentity $resource
 */
final class PathIdentityResource extends JsonResource
{
    /**
     * @return array{path: string, name: array{ar: string, en: string}, description: array{ar: string, en: string}}
     */
    public function toArray(Request $request): array
    {
        return $this->resource->toArray();
    }
}
