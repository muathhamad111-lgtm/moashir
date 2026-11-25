<?php

declare(strict_types=1);

namespace App\Http\Resources\Entities;

use App\Models\Entities\Entity;
use App\Support\Assessments\LastAccreditedAssessmentPresenter;
use App\Support\Certificates\EntityLevelPresenter;
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
            ...EntityLevelPresenter::of($entity),
            'last_level' => EntityLevelPresenter::lastLevelOf($entity),
            'has_open_accredited_request' => EntityLevelPresenter::hasOpenAccreditedRequest($entity),
            'last_accredited_assessment' => LastAccreditedAssessmentPresenter::of($entity),
            ...EntityPresenter::profile($entity),
            ...EntityPresenter::registry($entity, $request),
            ...EntityPresenter::contact($entity),
            ...EntityPresenter::billing($entity, $request),
            'billing_complete' => $entity->hasCompleteBilling(),
            'documents' => $this->when($this->showsDocuments($entity, $request), fn (): array => EntityPresenter::documents($entity, $request)),
            'marketing_consent' => $entity->marketing_consent_at !== null,
            'approved_at' => $entity->approved_at?->toIso8601String(),
            'rejected_at' => $entity->rejected_at?->toIso8601String(),
            'rejection_reason' => $entity->rejection_reason,
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

    /**
     * The documents belong to the entity's record only, never to the list, and only to the staff allowed to read them.
     */
    private function showsDocuments(Entity $entity, Request $request): bool
    {
        return $entity->relationLoaded('licenseCertificate') && $request->user()?->can('viewDocuments', $entity) === true;
    }
}
