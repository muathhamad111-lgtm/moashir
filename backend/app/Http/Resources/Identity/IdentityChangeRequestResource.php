<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Enums\Identity\IdentityField;
use App\Models\Identity\IdentityChangeRequest;
use App\Models\Identity\User;
use App\Support\Identity\AccountNationalId;
use App\Support\Identity\IdentityValueMask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read IdentityChangeRequest $resource
 */
final class IdentityChangeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $change = $this->resource;
        $user = $change->user;

        return [
            'id' => $change->public_id,
            'status' => $change->status->value,
            'fields' => array_map(static fn (IdentityField $field): string => $field->value, $change->fields()),
            'user' => $this->personOf($user),
            'submitted_by' => $this->personOf($change->submitter),
            'on_behalf' => $change->submitted_by_user_id !== $change->user_id,
            'current_name' => $user->name,
            'requested_name' => $change->new_name,
            'current_national_id_masked' => AccountNationalId::of($user)?->masked(),
            'requested_national_id_masked' => IdentityValueMask::of(IdentityField::NationalId, $change->new_national_id),
            'reason' => $change->reason,
            'reviewed_by' => $this->personOf($change->reviewer),
            'review_note' => $change->review_note,
            'created_at' => $change->created_at->toIso8601String(),
            'decided_at' => $change->decided_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function personOf(?User $user): ?array
    {
        return $user instanceof User ? ['id' => $user->id, 'name' => $user->name] : null;
    }
}
