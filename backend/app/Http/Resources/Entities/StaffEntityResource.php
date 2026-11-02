<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Entities\Entity;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Entity $resource
 */
final class StaffEntityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entity = $this->resource;

        return [
            ...EntityPresenter::summary($entity),
            ...EntityPresenter::profile($entity),
            ...EntityPresenter::contact($entity),
            'marketing_consent' => $entity->marketing_consent_at !== null,
            'deactivated_at' => $entity->deactivated_at?->toIso8601String(),
            'deactivation_reason' => $entity->deactivation_reason,
            'verification_reminded_at' => $entity->verification_reminded_at?->toIso8601String(),
            'is_deleted' => $entity->trashed(),
            'is_frozen' => $entity->trashed(),
            'deleted_at' => $entity->deleted_at?->toIso8601String(),
            'deletion_reason' => $entity->deletion_reason,
            'restored_at' => $entity->restored_at?->toIso8601String(),
        ];
    }
}
