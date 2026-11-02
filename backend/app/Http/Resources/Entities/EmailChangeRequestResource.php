<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Entities\Entity;
use App\Models\Identity\EmailChangeRequest;
use App\Support\Entities\EntityPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read EmailChangeRequest $resource
 */
final class EmailChangeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $change = $this->resource;

        return [
            'id' => $change->id,
            'entity' => $change->entity instanceof Entity ? EntityPresenter::summary($change->entity) : null,
            'old_email' => $change->old_email,
            'new_email' => $change->new_email,
            'policy' => $change->policy->value,
            'status' => $change->status->value,
            'requested_at' => $change->created_at->toIso8601String(),
            'otp_verified_at' => $change->otp_verified_at?->toIso8601String(),
            'applied_at' => $change->applied_at?->toIso8601String(),
            'decision_reason' => $change->decision_reason,
        ];
    }
}
