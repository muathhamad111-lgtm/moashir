<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Enums\Identity\IdentityField;
use App\Models\Identity\IdentityChangeRequest;
use App\Support\Identity\IdentityValueMask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read IdentityChangeRequest $resource
 */
final class OwnIdentityChangeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $change = $this->resource;

        return [
            'id' => $change->public_id,
            'status' => $change->status->value,
            'fields' => array_map(static fn (IdentityField $field): string => $field->value, $change->fields()),
            'requested_name' => $change->new_name,
            'requested_national_id_masked' => IdentityValueMask::of(IdentityField::NationalId, $change->new_national_id),
            'reason' => $change->reason,
            'review_note' => $change->review_note,
            'created_at' => $change->created_at->toIso8601String(),
            'decided_at' => $change->decided_at?->toIso8601String(),
        ];
    }
}
