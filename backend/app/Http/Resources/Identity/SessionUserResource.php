<?php

declare(strict_types=1);

namespace App\Http\Resources\Identity;

use App\Data\Identity\CommitteeAppointment;
use App\Data\Identity\SessionContext;
use App\Enums\Identity\AssessorQualification;
use App\Support\Assessments\RequestScopePresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read SessionContext $resource
 */
final class SessionUserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->resource->user;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale->value,
            'theme' => $user->theme->value,
            'roles' => RoleLabelResource::collection($this->resource->roles)->resolve($request),
            'permissions' => $this->resource->permissions,
            'assessor_qualifications' => array_map(static fn (AssessorQualification $qualification): string => $qualification->value, $user->qualifications()),
            'request_scope' => RequestScopePresenter::of($user),
            'is_super_admin' => $user->isSuperAdmin(),
            'mfa_enabled' => $user->hasConfirmedTotp(),
            'password_change_required' => $user->password_change_required,
            'entity_id' => $user->entity_id,
            'entity' => $this->resource->entity?->toArray(),
            'committee_appointments' => array_map(static fn (CommitteeAppointment $appointment): array => $appointment->toArray(), $this->resource->committeeAppointments),
            'session_expires_at' => $this->resource->expiresAt?->toIso8601String(),
            'idle_timeout_minutes' => $this->resource->idleTimeoutMinutes,
        ];
    }
}
