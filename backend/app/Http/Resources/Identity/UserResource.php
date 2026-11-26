<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Enums\Identity\AssessorQualification;
use App\Enums\Identity\RoleKey;
use App\Models\Identity\User;
use App\Support\Assessments\RequestScopePresenter;
use App\Support\Identity\AccountNationalId;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read User $resource
 */
final class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
            'email' => $this->resource->email,
            'phone' => $this->resource->phone,
            'gender' => $this->resource->gender?->value,
            'national_id_masked' => AccountNationalId::of($this->resource)?->masked(),
            'national_id_changed_at_acceptance_at' => $this->resource->relationLoaded('acceptanceNationalIdChange')
                ? $this->resource->acceptanceNationalIdChange?->created_at->toIso8601String()
                : null,
            'roles' => array_map(static fn (RoleKey $role): string => $role->value, $this->resource->roleKeys()),
            'assessor_qualifications' => array_map(static fn (AssessorQualification $qualification): string => $qualification->value, $this->resource->qualifications()),
            'request_scope' => RequestScopePresenter::of($this->resource),
            'status' => $this->resource->effectiveStatus()->value,
            'mfa_enabled' => $this->resource->hasConfirmedTotp(),
            'last_login_at' => $this->resource->last_login_at?->toIso8601String(),
            'entity_id' => $this->resource->entity_id,
            'created_at' => $this->resource->created_at->toIso8601String(),
        ];
    }
}
