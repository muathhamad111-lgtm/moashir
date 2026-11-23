<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Enums\Identity\EmailChangeStatus;
use App\Models\Entities\Entity;
use App\Models\Entities\EntityProfileChangeRequest;
use App\Models\Identity\EmailChangeRequest;
use App\Support\Entities\EntityPresenter;
use App\Support\Entities\ProfileFieldValues;
use App\Support\Entities\ProfileSectionsPresenter;
use App\Support\ValueObjects\EmailAddress;
use App\Support\ValueObjects\ProfileCompletion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read Entity $resource
 */
final class OwnEntityResource extends JsonResource
{
    public const array RELATIONS = ['owner', 'region', 'city', 'emailChangeRequests', 'logo', 'vatCertificate', 'pendingProfileChange'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $entity = $this->resource;
        $values = ProfileFieldValues::of($entity);

        return [
            ...EntityPresenter::summary($entity),
            ...EntityPresenter::profile($entity),
            ...EntityPresenter::registry($entity, $request),
            ...EntityPresenter::contact($entity),
            ...EntityPresenter::billing($entity, $request),
            'billing_complete' => $entity->hasCompleteBilling(),
            'marketing_consent' => $entity->marketing_consent_at !== null,
            'marketing_consent_at' => $entity->marketing_consent_at?->toIso8601String(),
            'pending_email_change' => $this->pendingEmailChange($entity),
            'pending_profile_change' => $this->pendingProfileChange($entity),
            'can_edit' => $request->user()?->can('updateOwn', $entity) === true,
            'profile_sections' => ProfileSectionsPresenter::present($values),
            'profile_completion' => ProfileCompletion::of($values)->toArray(),
        ];
    }

    /**
     * @return array{id: string, revision: int, items: list<string>, submitted_at: string, updated_at: string}|null
     */
    private function pendingProfileChange(Entity $entity): ?array
    {
        $pending = $entity->pendingProfileChange;

        return $pending instanceof EntityProfileChangeRequest ? [
            'id' => $pending->public_id,
            'revision' => $pending->revision,
            'items' => $pending->items(),
            'submitted_at' => $pending->created_at->toIso8601String(),
            'updated_at' => $pending->updated_at->toIso8601String(),
        ] : null;
    }

    /**
     * @return array{status: string, new_email: string, policy: string, requested_at: string}|null
     */
    private function pendingEmailChange(Entity $entity): ?array
    {
        $pending = $entity->emailChangeRequests
            ->first(static fn (EmailChangeRequest $change): bool => $change->status === EmailChangeStatus::AwaitingApproval);

        return $pending instanceof EmailChangeRequest ? [
            'status' => $pending->status->value,
            'new_email' => new EmailAddress($pending->new_email)->masked(),
            'policy' => $pending->policy->value,
            'requested_at' => $pending->created_at->toIso8601String(),
        ] : null;
    }
}
