<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Entities\PrivacyRequest;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read PrivacyRequest $resource
 */
final class PrivacyRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $privacy = $this->resource;

        return [
            'id' => $privacy->id,
            'entity' => EntityPresenter::summary($privacy->entity),
            'kind' => $privacy->kind->value,
            'status' => $privacy->status->value,
            'details' => $privacy->details,
            'created_by' => ['id' => $privacy->createdBy->id, 'name' => $privacy->createdBy->name],
            'decided_by' => $privacy->decidedBy === null ? null : ['id' => $privacy->decidedBy->id, 'name' => $privacy->decidedBy->name],
            'decision_note' => $privacy->decision_note,
            'decided_at' => $privacy->decided_at?->toIso8601String(),
            'created_at' => $privacy->created_at->toIso8601String(),
        ];
    }
}
