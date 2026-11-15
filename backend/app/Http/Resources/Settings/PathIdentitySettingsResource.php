<?php

declare(strict_types=1);

namespace App\Http\Resources\Settings;

use App\Data\Paths\PathIdentity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The settings of a path that only carries a description (the self assessment); its label is fixed and read-only.
 *
 * @property-read PathIdentity $resource
 */
final class PathIdentitySettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $identity = $this->resource;

        return [
            'path' => $identity->path->value,
            'path_label' => $identity->name->toArray(),
            'description' => $identity->description->toArray(),
        ];
    }
}
