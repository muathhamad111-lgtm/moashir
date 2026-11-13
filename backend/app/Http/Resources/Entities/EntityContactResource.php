<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Entities\Entity;
use App\Support\Certificates\EntityLevelPresenter;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Entity $resource
 */
final class EntityContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...EntityPresenter::summary($this->resource),
            ...EntityLevelPresenter::of($this->resource),
            'last_level' => EntityLevelPresenter::lastLevelOf($this->resource),
            ...EntityPresenter::contact($this->resource),
        ];
    }
}
