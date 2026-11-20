<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Models\Identity\User;
use App\Models\Identity\UserIdentityChange;
use App\Support\Identity\IdentityValueMask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read UserIdentityChange $resource
 */
final class IdentityHistoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $change = $this->resource;

        return [
            'id' => $change->id,
            'field' => $change->field->value,
            'old_value' => IdentityValueMask::of($change->field, $change->old_value),
            'new_value' => IdentityValueMask::of($change->field, $change->new_value),
            'request_id' => $change->request->public_id,
            'reason' => $change->request->reason,
            'requested_by' => $this->personOf($change->requester),
            'approved_by' => $this->personOf($change->approver),
            'created_at' => $change->created_at->toIso8601String(),
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
